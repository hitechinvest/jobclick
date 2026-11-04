<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Platform: string implements HasLabel
{
    public function getLabel(): string
    {
        return $this->label();
    }

    case YandexSearch = 'yandex_search';
    case YandexRsya = 'yandex_rsya';
    case Vkontakte = 'vkontakte';
    case Odnoklassniki = 'odnoklassniki';
    case VkNetwork = 'vk_network';

    public function label(): string
    {
        return match ($this) {
            self::YandexSearch => 'Яндекс Поиск',
            self::YandexRsya => 'Рекламная сеть Яндекса',
            self::Vkontakte => 'ВКонтакте',
            self::Odnoklassniki => 'Одноклассники',
            self::VkNetwork => 'Рекламная сеть VK',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::YandexSearch => 'Текстовые объявления в результатах поиска Яндекса, когда человек сам ищет работу: «вакансии курьер Казань». Самые «горячие» кандидаты, но объём меньше.',
            self::YandexRsya => 'Объявления с картинкой на тысячах сайтов-партнёров Яндекса, в Дзене, Картах, Почте и приложениях. Главный источник кликов, нужна картинка.',
            self::Vkontakte => 'Лента новостей, клипы и истории ВКонтакте — самой популярной соцсети России. Подходит для массовых и молодёжных вакансий.',
            self::Odnoklassniki => 'Лента и блоки в Одноклассниках. Аудитория 35+ и жители регионов: отлично для вакансий водителей, охранников, продавцов, рабочих специальностей.',
            self::VkNetwork => 'Сайты и приложения-партнёры VK: Почта Mail.ru, Юла, мобильные игры и сервисы. Дешёвый широкий охват.',
        };
    }

    public const VK_GROUP_LABEL = 'VK Реклама';

    public const VK_GROUP_HINT = 'Рекламная платформа VK: одна кампания показывается сразу во ВКонтакте, Одноклассниках и на сайтах-партнёрах VK. Хороший охват людей, которые пока не ищут работу активно, но откликнутся на интересное предложение.';

    public const ALL_HINT = 'Мы покажем объявление везде, где есть ваши кандидаты, и сами распределим бюджет между площадками. Рекомендуем для быстрого закрытия вакансии.';

    public function group(): string
    {
        return in_array($this, [self::YandexSearch, self::YandexRsya], true) ? 'Яндекс' : 'VK';
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $p) => [$p->value => $p->label()])->all();
    }
}
