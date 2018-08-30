<?php

declare(strict_types=1);

namespace Wingu\Plugin\Wordpress;

class WinguLoader
{
    protected $actions;
    protected $filters;

    public function __construct()
    {
        $this->actions = [];
        $this->filters = [];
    }

    /**
     * @var      string $hook      The name of the WordPress action that is being registered.
     * @var      object $component A reference to the instance of the object on which the action is defined.
     * @var      string $callback  The name of the function definition on the $component.
     * @var      int      Optional    $priority         The priority at which the function should be fired.
     * @var      int      Optional    $accepted_args    The number of arguments that should be passed to the $callback.
     */
    public function addAction($hook, $component, $callback, $priority = 10, $accepted_args = 1) : void
    {
        $this->actions = $this->add($this->actions, $hook, $component, $callback, $priority, $accepted_args);
    }

    /**
     * @var      string $hook      The name of the WordPress filter that is being registered.
     * @var      object $component A reference to the instance of the object on which the filter is defined.
     * @var      string $callback  The name of the function definition on the $component.
     * @var      int      Optional    $priority         The priority at which the function should be fired.
     * @var      int      Optional    $accepted_args    The number of arguments that should be passed to the $callback.
     */
    public function addFilter($hook, $component, $callback, $priority = 10, $accepted_args = 1) : void
    {
        $this->filters = $this->add($this->filters, $hook, $component, $callback, $priority, $accepted_args);
    }

    /**
     * @var      array  $hooks     The collection of hooks that is being registered (that is, actions or filters).
     * @var      string $hook      The name of the WordPress filter that is being registered.
     * @var      object $component A reference to the instance of the object on which the filter is defined.
     * @var      string $callback  The name of the function definition on the $component.
     * @var      int      Optional    $priority         The priority at which the function should be fired.
     * @var      int      Optional    $accepted_args    The number of arguments that should be passed to the $callback.
     * @return   mixed[] The collection of actions and filters registered with WordPress.
     */
    private function add($hooks, $hook, $component, $callback, $priority, $accepted_args) : array
    {
        $hooks[] = [
            'hook' => $hook,
            'component' => $component,
            'callback' => $callback,
            'priority' => $priority,
            'accepted_args' => $accepted_args,
        ];

        return $hooks;
    }

    public function run() : void
    {
        foreach ($this->filters as $hook) {
            add_filter($hook['hook'], [$hook['component'], $hook['callback']], $hook['priority'],
                $hook['accepted_args']);
        }

        foreach ($this->actions as $hook) {
            add_action($hook['hook'], [$hook['component'], $hook['callback']], $hook['priority'],
                $hook['accepted_args']);
        }
    }
}
