<?php
declare(strict_types=1);

namespace GI\Core;

class View
{
    private static string $viewsPath = '';

    public static function setViewsPath(string $path): void
    {
        self::$viewsPath = $path;
    }

    public static function render(string $view, array $data = [], string $layout = 'main'): void
    {
        $viewsPath = self::$viewsPath ?: BASE_PATH . '/views';

        extract($data, EXTR_SKIP);

        $viewFile = $viewsPath . '/' . str_replace('.', '/', $view) . '.php';

        if (!file_exists($viewFile)) {
            throw new \RuntimeException("View file not found: {$viewFile}");
        }

        ob_start();
        include $viewFile;
        $content = ob_get_clean();

        $layoutFile = $viewsPath . '/layouts/' . $layout . '.php';
        if (!empty($layout) && file_exists($layoutFile)) {
            include $layoutFile;
        } else {
            echo $content;
        }
    }

    public static function renderPartial(string $view, array $data = []): string
    {
        $viewsPath = self::$viewsPath ?: BASE_PATH . '/views';
        extract($data, EXTR_SKIP);
        $viewFile = $viewsPath . '/' . str_replace('.', '/', $view) . '.php';
        ob_start();
        include $viewFile;
        return (string) ob_get_clean();
    }
}
