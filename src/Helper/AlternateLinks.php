<?php

declare(strict_types=1);

namespace Terminal42\ChangeLanguage\Helper;

use Contao\Environment;
use Contao\FrontendTemplate;
use Terminal42\ChangeLanguage\Language;
use Terminal42\ChangeLanguage\Navigation\NavigationItem;
use Terminal42\ChangeLanguage\Navigation\UrlParameterBag;

/**
 * AlternateLinks is a helper class to handle <link rel="alternate"> in the page header.
 */
class AlternateLinks
{
    /**
     * @var array
     */
    private $links = [];

    /**
     * Returns whether a link already exists for a language.
     *
     * @param string $language
     *
     * @return bool
     */
    public function has($language)
    {
        return \array_key_exists(Language::toLanguageTag($language), $this->links);
    }

    /**
     * Adds or replaces a link for a language.
     *
     * @param string $language
     * @param string $href
     * @param string $title
     */
    public function add($language, $href, $title = ''): void
    {
        $this->store($language, $href, $title);
    }

    /**
     * Adds a link from a NavigationItem instance.
     */
    public function addFromNavigationItem(NavigationItem $item, UrlParameterBag $urlParameterBag): void
    {
        $this->add($item->getLanguageTag(), $item->getHref($urlParameterBag), $item->getTitle());
    }

    /**
     * Removes link for a language if it exists.
     *
     * @param string $language
     */
    public function remove($language): void
    {
        unset($this->links[Language::toLanguageTag($language)]);
    }

    /**
     * Sets link for the x-default language.
     *
     * @param string $href
     * @param string $title
     */
    public function setDefault($href, $title = ''): void
    {
        $this->store('x-default', $href, $title);
    }

    /**
     * Generates template markup of links for the page header.
     *
     * @param string $templateName
     *
     * @return string
     */
    public function generate($templateName = 'block_alternate_links')
    {
        if (0 === \count($this->links)) {
            return '';
        }

        $template = new FrontendTemplate($templateName);

        $template->links = $this->links;

        return $template->parse();
    }

    /**
     * @param string $language
     * @param string $href
     * @param string $title
     */
    private function store($language, $href, $title): void
    {
        $language = Language::toLanguageTag($language);

        // URLs must always be absolute
        if (0 !== strpos($href, 'http://') && 0 !== strpos($href, 'https://')) {
            $href = Environment::get('base').$href;
        }

        $this->links[$language] = ['language' => $language, 'href' => $href, 'title' => $title];
    }
}
