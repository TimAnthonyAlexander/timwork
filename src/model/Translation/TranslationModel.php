<?php

namespace src\model\Translation;

use src\module\InstantCache\InstantCache;
use src\module\TranslationConfig\TranslationConfig;

class TranslationModel extends TranslationConfig
{
    public string $id;
    public string $english = '';
    public string $german = '';
    public string $french = '';
    public string $spanish = '';
    public string $italian = '';
    public string $portuguese = '';
    public string $georgian = ''; // Example: ქართული

    public static function getTranslation(string $id, bool $ucFirst = true): string {
        if (!InstantCache::isset('translationmodel')) {
            InstantCache::set('translationmodel', new TranslationModel());
        }

        $translationModel = InstantCache::get('translationmodel');
        assert($translationModel instanceof TranslationModel);

        $language = 'english';
        $translation = $translationModel->getConfigItem($id, $translationModel->toArray())[$language] ?? $id;

        if ($ucFirst) {
            $translation = ucfirst((string) $translation);
        }

        return $translation;
    }
}
