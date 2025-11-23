<?php

declare(strict_types=1);

namespace BetterAuth\Core\Plugin\Events;

use BetterAuth\Core\Plugin\PluginInterface;

/**
 * Event dispatched when a plugin is successfully loaded.
 */
class PluginLoadedEvent
{
    public function __construct(
        public readonly PluginInterface $plugin
    ) {
    }

    /**
     * Get the plugin name.
     *
     * @return string
     */
    public function getPluginName(): string
    {
        return $this->plugin->getName();
    }

    /**
     * Get the plugin version.
     *
     * @return string
     */
    public function getPluginVersion(): string
    {
        return $this->plugin->getVersion();
    }

    /**
     * Get the plugin instance.
     *
     * @return PluginInterface
     */
    public function getPlugin(): PluginInterface
    {
        return $this->plugin;
    }
}
