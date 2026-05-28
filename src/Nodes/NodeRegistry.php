<?php

namespace LoggedCloud\CaddyStudio\Nodes;

class NodeRegistry
{
    /** @var array<string, class-string<CaddyNodeType>> */
    protected static array $registered = [];

    /**
     * Register a node class. Throws on bad shape so misconfigurations fail
     * loudly at boot instead of silently dropping the node from the palette.
     */
    public static function register(string $class): void
    {
        if (! class_exists($class)) {
            throw new \InvalidArgumentException("Node class $class does not exist.");
        }
        if (! is_subclass_of($class, CaddyNodeType::class)) {
            throw new \InvalidArgumentException("$class must extend ".CaddyNodeType::class.'.');
        }
        $key = $class::key();
        if ($key === '') {
            throw new \InvalidArgumentException("$class::key() must return a non-empty identifier.");
        }
        self::$registered[$key] = $class;
    }

    /** @return array<string, class-string<CaddyNodeType>> */
    public static function all(): array
    {
        return self::$registered;
    }

    /** @return class-string<CaddyNodeType>|null */
    public static function find(string $key): ?string
    {
        return self::$registered[$key] ?? null;
    }

    /** Library-entry map keyed by node key · what the palette + canvas read. */
    public static function library(): array
    {
        $out = [];
        foreach (self::$registered as $key => $class) {
            $out[$key] = $class::toLibraryEntry();
        }

        return $out;
    }

    public static function clear(): void
    {
        self::$registered = [];
    }
}
