<?php

namespace App\Services\Parsers;

use App\Services\Integrations\SafeHttp;
use Symfony\Component\DomCrawler\Crawler;
use Throwable;

/** Достаёт вакансию из HTML: schema.org JobPosting (JSON-LD) → og-теги → текст страницы, плюс все картинки. */
class HtmlExtractor
{
    public function extract(string $html, string $url, string $site): ParsedVacancy
    {
        $vacancy = new ParsedVacancy($site);

        try {
            $crawler = new Crawler($html, $url);
        } catch (Throwable) {
            return $vacancy;
        }

        $this->fromJsonLd($crawler, $vacancy, $url);

        $meta = fn (string $name) => $this->attr($crawler, 'meta[property="'.$name.'"], meta[name="'.$name.'"]', 'content');
        $vacancy->title ??= $this->clean($meta('og:title') ?: $this->text($crawler, 'h1') ?: $this->text($crawler, 'title'));
        $ogDescription = $this->clean($meta('og:description') ?: $meta('description'));

        if ($og = $meta('og:image')) {
            array_unshift($vacancy->images, SafeHttp::absolute($og, $url));
        }

        foreach ($crawler->filter('img') as $img) {
            $src = $img->getAttribute('src') ?: $img->getAttribute('data-src') ?: $this->firstFromSrcset($img->getAttribute('srcset'));
            if ($src && ! str_starts_with($src, 'data:') && ! preg_match('/\.(svg|gif)(\?|$)/i', $src)) {
                $vacancy->images[] = SafeHttp::absolute($src, $url);
            }
            if (count($vacancy->images) >= 40) {
                break;
            }
        }
        $vacancy->images = array_values(array_unique($vacancy->images));

        $vacancy->rawText = $this->pageText($crawler);
        $vacancy->description ??= $ogDescription;
        $vacancy->blocked = $this->looksBlocked($html, $vacancy);

        return $vacancy;
    }

    protected function fromJsonLd(Crawler $crawler, ParsedVacancy $vacancy, string $url): void
    {
        foreach ($crawler->filter('script[type="application/ld+json"]') as $node) {
            $data = json_decode(trim($node->textContent), true);
            if (! is_array($data)) {
                continue;
            }

            $job = $this->findJobPosting($data);
            if (! $job) {
                continue;
            }

            $vacancy->title = $this->clean($job['title'] ?? null) ?: null;
            $vacancy->description = $this->clean(strip_tags(str_replace(['</p>', '<br>', '<br/>', '</li>'], "\n", html_entity_decode((string) ($job['description'] ?? ''))))) ?: null;

            $org = $job['hiringOrganization'] ?? [];
            if (is_array($org)) {
                $vacancy->employer = $this->clean($org['name'] ?? null) ?: null;
                $logo = is_array($org['logo'] ?? null) ? ($org['logo']['url'] ?? null) : ($org['logo'] ?? null);
                $vacancy->logo = $logo ? SafeHttp::absolute((string) $logo, $url) : null;
            }

            $location = $job['jobLocation'] ?? null;
            if (is_array($location) && array_is_list($location)) {
                $location = $location[0] ?? null;
            }
            $address = is_array($location) ? ($location['address'] ?? null) : null;
            $vacancy->city = is_array($address) ? ($address['addressLocality'] ?? $address['addressRegion'] ?? null) : (is_string($address) ? $address : null);

            $salary = $job['baseSalary'] ?? null;
            if (is_array($salary)) {
                $value = $salary['value'] ?? [];
                $vacancy->salary = is_array($value)
                    ? ParsedVacancy::salaryText($value['minValue'] ?? $value['value'] ?? null, $value['maxValue'] ?? null, $salary['currency'] ?? 'RUB')
                    : ParsedVacancy::salaryText((float) $value, null, $salary['currency'] ?? 'RUB');
            }

            $type = $job['employmentType'] ?? null;
            $vacancy->schedule = is_array($type) ? implode(', ', $type) : $type;

            if (! empty($job['image'])) {
                $vacancy->images[] = SafeHttp::absolute(is_array($job['image']) ? (string) ($job['image']['url'] ?? $job['image'][0] ?? '') : (string) $job['image'], $url);
            }

            if (! empty($job['validThrough']) && strtotime((string) $job['validThrough']) && strtotime((string) $job['validThrough']) < time()) {
                $vacancy->archived = true;
            }

            return;
        }
    }

    protected function findJobPosting(array $data): ?array
    {
        $type = $data['@type'] ?? null;
        if ($type === 'JobPosting' || (is_array($type) && in_array('JobPosting', $type, true))) {
            return $data;
        }

        foreach (array_is_list($data) ? $data : ($data['@graph'] ?? []) as $item) {
            if (is_array($item) && ($found = $this->findJobPosting($item))) {
                return $found;
            }
        }

        return null;
    }

    protected function pageText(Crawler $crawler): ?string
    {
        try {
            $body = $crawler->filter('body');
            if (! $body->count()) {
                return null;
            }
            $dom = $body->getNode(0)->cloneNode(true);
            foreach (['script', 'style', 'noscript', 'svg', 'header', 'footer', 'nav'] as $tag) {
                $remove = [];
                foreach ($dom->getElementsByTagName($tag) as $el) {
                    $remove[] = $el;
                }
                foreach ($remove as $el) {
                    $el->parentNode?->removeChild($el);
                }
            }

            return mb_substr($this->clean($dom->textContent), 0, 8000) ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    protected function looksBlocked(string $html, ParsedVacancy $vacancy): bool
    {
        if ($vacancy->description && mb_strlen($vacancy->description) > 200) {
            return false;
        }

        return (bool) preg_match('/captcha|капч|доступ ограничен|подозрительн|are you a robot|access denied|blocked/iu', mb_substr($html, 0, 200000));
    }

    protected function attr(Crawler $crawler, string $selector, string $attr): ?string
    {
        try {
            $node = $crawler->filter($selector);

            return $node->count() ? $node->first()->attr($attr) : null;
        } catch (Throwable) {
            return null;
        }
    }

    protected function text(Crawler $crawler, string $selector): ?string
    {
        try {
            $node = $crawler->filter($selector);

            return $node->count() ? $node->first()->text('') : null;
        } catch (Throwable) {
            return null;
        }
    }

    protected function firstFromSrcset(?string $srcset): ?string
    {
        return $srcset ? trim(explode(' ', trim(explode(',', $srcset)[0]))[0]) : null;
    }

    public function clean(?string $text): string
    {
        $text = html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t\x{00A0}]+/u", ' ', $text);
        $text = preg_replace("/\s*\n\s*/u", "\n", $text);

        return trim(preg_replace("/\n{3,}/u", "\n\n", $text));
    }
}
