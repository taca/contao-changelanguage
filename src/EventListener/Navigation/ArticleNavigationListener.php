<?php

declare(strict_types=1);

namespace Terminal42\ChangeLanguage\EventListener\Navigation;

use Contao\ArticleModel;
use Contao\Database;
use Contao\Date;
use Terminal42\ChangeLanguage\Event\ChangelanguageNavigationEvent;
use Terminal42\ChangeLanguage\PageFinder;

class ArticleNavigationListener
{
    /**
     * Translate URL parameters for articles.
     */
    public function onChangelanguageNavigation(ChangelanguageNavigationEvent $event): void
    {
        // Try to find matching article
        if (
            $event->getNavigationItem()->isCurrentPage()
            || !$event->getUrlParameterBag()->hasUrlAttribute('articles')
        ) {
            return;
        }

        /** @var PageModel $objPage */
        global $objPage;

        $parameterBag = $event->getUrlParameterBag();
        $currentArticle = ArticleModel::findByIdOrAliasAndPid(
            $parameterBag->getUrlAttribute('articles'),
            $objPage->id
        );

        if (null === $currentArticle) {
            return;
        }

        $pageFinder = new PageFinder();
        $targetRoot = $event->getNavigationItem()->getRootPage();
        $masterRoot = $pageFinder->findMasterRootForPage($targetRoot);

        $targetArticle = $this->findTargetArticle(
            $currentArticle,
            $targetRoot->id,
            $objPage->rootId === $masterRoot->id,
            null !== $masterRoot && $targetRoot->id === $masterRoot->id
        );

        if (null === $targetArticle) {
            $parameterBag->removeUrlAttribute('articles');
        } else {
            $parameterBag->setUrlAttribute('articles', $targetArticle->alias ?: $targetArticle->id);
        }
    }

    /**
     * Find target article for a root page and current article.
     *
     * @param int  $targetRootId
     * @param bool $currentIsFallback
     * @param bool $targetIsFallback
     *
     * @return ArticleModel|null
     */
    private function findTargetArticle(ArticleModel $currentArticle, $targetRootId, $currentIsFallback, $targetIsFallback)
    {
        // If the target root is fallback, the article ID will match our current "languageMain"
        if ($targetIsFallback) {
            return $this->findPublishedArticle(['tl_article.id = '.$currentArticle->languageMain]);
        }

        $subpages = Database::getInstance()->getChildRecords($targetRootId, 'tl_page');

        if (0 === \count($subpages)) {
            return null;
        }

        return $this->findPublishedArticle(
            [
                'tl_article.languageMain = ?',
                'tl_article.pid IN ('.implode(',', $subpages).')',
            ],
            [
                $currentIsFallback ? $currentArticle->id : $currentArticle->languageMain,
            ]
        );
    }

    /**
     * Find a published article with additional conditions.
     *
     * @return ArticleModel|null
     */
    private function findPublishedArticle(array $columns, array $values = [], array $options = [])
    {
        if (true !== BE_USER_LOGGED_IN) {
            $time = Date::floorToMinute();
            $columns[] = "(tl_article.start='' OR tl_article.start<='$time')";
            $columns[] = "(tl_article.stop='' OR tl_article.stop>'".($time + 60)."')";
            $columns[] = "tl_article.published='1'";
        }

        return ArticleModel::findOneBy($columns, $values, $options);
    }
}
