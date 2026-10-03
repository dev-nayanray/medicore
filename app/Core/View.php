<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Lightweight template engine — plain PHP views with layouts & sections.
 *
 * View::make('admin/dashboard', $data)->render()
 *
 * Inside a view:
 *   $this->extend('layouts/admin');      // wrap in a layout
 *   $this->section('title'); ?> ... <?php $this->end();
 *   echo $this->yield('title');          // inside the layout
 *   echo $this->insert('components/badge', ['variant' => 'teal']);
 */
final class View
{
    private const VIEW_PATH = BASE_PATH . '/resources/views/';

    /** @var array<string, mixed> data shared with every view */
    private static array $shared = [];

    /** @var array<string, string> captured sections */
    private array $sections = [];

    /** @var array<int, string> section names being captured */
    private array $sectionStack = [];

    private ?string $layout = null;

    private function __construct(
        private readonly string $view,
        private readonly array $data = [],
    ) {
    }

    public static function make(string $view, array $data = []): self
    {
        return new self($view, $data);
    }

    /** Share a variable with every subsequently rendered view. */
    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    /** @return array<string, mixed> */
    public static function shared(): array
    {
        return self::$shared;
    }

    // ------------------------------------------------------------------
    // Template directives (called from within view files)
    // ------------------------------------------------------------------
    public function extend(string $layout): void
    {
        $this->layout = $layout;
    }

    public function section(string $name): void
    {
        $this->sectionStack[] = $name;
        ob_start();
    }

    public function end(): void
    {
        $name = array_pop($this->sectionStack);
        if ($name === null) {
            throw new RuntimeException('View::end() called without a matching section().');
        }
        $this->sections[$name] = (string) ob_get_clean();
    }

    public function yield(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    /** Include a partial with its own local data merged over the parent scope. */
    public function insert(string $view, array $data = []): string
    {
        $inner = new self($view, array_merge($this->data, $data));
        $inner->sections = &$this->sections;     // sections stay shareable
        $inner->sectionStack = &$this->sectionStack;
        return $inner->renderNoLayout();
    }

    public function exists(string $view): bool
    {
        return is_file(self::VIEW_PATH . str_replace(['.', '\\'], '/', $view) . '.php');
    }

    // ------------------------------------------------------------------
    // Rendering
    // ------------------------------------------------------------------
    public function render(): string
    {
        $content = $this->renderFile($this->view, $this->data);

        if ($this->layout !== null) {
            $layout = $this->layout;
            $this->layout = null;
            // Anything echoed outside a named section becomes "content".
            if (!isset($this->sections['content'])) {
                $this->sections['content'] = $content;
            }
            return $this->renderFile($layout, $this->data);
        }

        return $content;
    }

    private function renderNoLayout(): string
    {
        return $this->renderFile($this->view, $this->data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderFile(string $view, array $data): string
    {
        $file = self::VIEW_PATH . str_replace(['.', '\\'], '/', $view) . '.php';
        if (!is_file($file)) {
            throw new RuntimeException("View [{$view}] not found at {$file}");
        }

        // Template scope: extracted data (wins) + shared data + $this (View).
        extract($data, EXTR_SKIP);
        extract(self::$shared, EXTR_SKIP);

        ob_start();
        try {
            require $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
