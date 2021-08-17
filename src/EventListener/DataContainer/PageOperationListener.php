<?php

declare(strict_types=1);

namespace Terminal42\ChangeLanguage\EventListener\DataContainer;

use Contao\Database;
use Contao\DataContainer;
use Contao\PageModel;
use Terminal42\ChangeLanguage\PageFinder;

class PageOperationListener
{
    public function register(): void
    {
        $GLOBALS['TL_DCA']['tl_page']['config']['oncopy_callback'][] = $this->selfCallback('onCopy');
        $GLOBALS['TL_DCA']['tl_page']['config']['oncut_callback'][] = $this->selfCallback('onCut');
        $GLOBALS['TL_DCA']['tl_page']['config']['onsubmit_callback'][] = $this->selfCallback('onSubmit');
        $GLOBALS['TL_DCA']['tl_page']['config']['ondelete_callback'][] = $this->selfCallback('onDelete');
        $GLOBALS['TL_DCA']['tl_page']['config']['onundo_callback'][] = $this->selfCallback('onUndo');
    }

    /**
     * Handles submitting a page and resets tl_page.languageMain if necessary.
     */
    public function onSubmit(DataContainer $dc): void
    {
        if (
            'root' === $dc->activeRecord->type
            && $dc->activeRecord->fallback
            && (!$dc->activeRecord->languageRoot || null === PageModel::findByPk($dc->activeRecord->languageRoot))
        ) {
            $this->resetPageAndChildren($dc->id);
        }
    }

    /**
     * Handles copying a page and resets tl_page.languageMain if necessary.
     *
     * @param int $insertId
     */
    public function onCopy($insertId): void
    {
        $this->validateLanguageMainForPage($insertId);
    }

    /**
     * Handles moving a page and resets tl_page.languageMain if necessary.
     */
    public function onCut(DataContainer $dc): void
    {
        $this->validateLanguageMainForPage($dc->id);
    }

    /**
     * Handles deleting a page and resets tl_page.languageMain if necessary.
     */
    public function onDelete(DataContainer $dc): void
    {
        $this->resetPageAndChildren($dc->id);
    }

    /**
     * Handles undo of a deleted page and resets tl_page.languageMain if necessary.
     *
     * @param string $table
     */
    public function onUndo($table, array $row): void
    {
        $this->validateLanguageMainForPage($row['id']);
    }

    private function validateLanguageMainForPage($pageId): void
    {
        $page = PageModel::findWithDetails($pageId);

        // Moving a root page does not affect language assignments
        if (null === $page || !$page->languageMain || 'root' === $page->type) {
            return;
        }

        $duplicates = PageModel::countBy(
            [
                'id IN ('.implode(',', Database::getInstance()->getChildRecords($page->rootId, 'tl_page')).')',
                'languageMain=?',
                'id!=?',
            ],
            [$page->languageMain, $page->id]
        );

        // Reset languageMain if another page in the new page tree has the same languageMain
        if ($duplicates > 0) {
            $this->resetPageAndChildren($page->id);

            return;
        }

        $pageFinder = new PageFinder();
        $masterRoot = $pageFinder->findMasterRootForPage($page);

        // Reset languageMain if current tree has no master or if it's the master tree
        if (null === $masterRoot || $masterRoot->id === $page->rootId) {
            $this->resetPageAndChildren($page->id);

            return;
        }

        // Reset languageMain if the current value is not a valid ID of the master tree
        if (!\in_array($page->languageMain, Database::getInstance()->getChildRecords($masterRoot->id, 'tl_page'), false)) {
            Database::getInstance()
                ->prepare('UPDATE tl_page SET languageMain=0 WHERE id=?')
                ->execute($page->id)
            ;
        }
    }

    /**
     * @param int $pageId
     */
    private function resetPageAndChildren($pageId): void
    {
        $resetIds = Database::getInstance()->getChildRecords($pageId, 'tl_page');
        $resetIds[] = $pageId;

        Database::getInstance()->query(
            'UPDATE tl_page SET languageMain=0 WHERE id IN ('.implode(',', $resetIds).')'
        );
    }

    /**
     * @param $method
     *
     * @return \Closure
     */
    private function selfCallback($method)
    {
        return function () use ($method) {
            return \call_user_func_array(
                [$this, $method],
                \func_get_args()
            );
        };
    }
}
