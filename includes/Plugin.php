<?php

namespace Clio\CentPmpro;

defined('ABSPATH') || exit;

class Plugin
{
    private static ?Plugin $instance = null;

    public string $file;
    public string $dir;
    public string $url;

    public Rules $rules;
    public Admin $admin;
    public Bridge $bridge;

    private function __construct(string $file)
    {
        $this->file = $file;
        $this->dir  = plugin_dir_path($file);
        $this->url  = plugin_dir_url($file);

        spl_autoload_register([$this, 'autoload']);

        register_activation_hook($file, [Schema::class, 'activate']);
    }

    public static function getInstance(?string $file = null): self
    {
        if (self::$instance === null) {
            if ($file === null) {
                throw new \LogicException('Clio CENT — PMPro Bridge has not been bootstrapped yet.');
            }
            self::$instance = new self($file);
        }

        return self::$instance;
    }

    public function autoload(string $class): void
    {
        $prefix = __NAMESPACE__ . '\\';

        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            return;
        }

        $path = $this->dir . 'includes/' . substr($class, strlen($prefix)) . '.php';

        if (is_readable($path)) {
            require_once $path;
        }
    }

    public function boot(): void
    {
        Schema::maybeUpgrade();

        $this->rules  = new Rules($this);
        $this->admin  = new Admin($this);
        $this->bridge = new Bridge($this);

        $this->admin->register();
        $this->bridge->register();
    }

    public function view(string $name, array $data = []): void
    {
        $file = $this->dir . 'views/' . $name . '.php';

        if (! file_exists($file)) {
            return;
        }

        extract($data, EXTR_SKIP);
        include $file;
    }

    public function __clone() {}
    public function __wakeup() {}
}
