<?php

namespace Egerstudios\TenantModules\Traits;

use Illuminate\Support\Facades\Config;

trait HasTranslations
{
    /**
     * Get the module's display name in the current locale
     *
     * @return string
     */
    public function getDisplayName(): string
    {
        $locale = app()->getLocale();
        $fallbackLocale = config('app.fallback_locale', 'en');
        
        $displayNames = Config::get("{$this->moduleNameLower}.display_names", []);
        
        return $displayNames[$locale] ?? $displayNames[$fallbackLocale] ?? $this->moduleName;
    }
    
    /**
     * Get a translated string from the module's language files
     *
     * @param string $key
     * @param array $replace
     * @param string|null $locale
     * @return string
     */
    public function trans(string $key, array $replace = [], ?string $locale = null): string
    {
        return __("{$this->moduleNameLower}::{$key}", $replace, $locale);
    }
    
    /**
     * Get a translated choice from the module's language files
     *
     * @param string $key
     * @param int $number
     * @param array $replace
     * @param string|null $locale
     * @return string
     */
    public function transChoice(string $key, int $number, array $replace = [], ?string $locale = null): string
    {
        return trans_choice("{$this->moduleNameLower}::{$key}", $number, $replace, $locale);
    }
} 