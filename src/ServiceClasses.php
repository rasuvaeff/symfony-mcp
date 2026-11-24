<?php

declare(strict_types=1);

namespace Rasuvaeff\SymfonyMcp;

/**
 * Every service the options name. Symfony hands out private services only
 * through a locator, so the classes must be known when the container is built.
 *
 * @internal
 */
final readonly class ServiceClasses
{
    private const array LISTS = ['tools', 'interceptors', 'prompt_interceptors', 'resource_interceptors', 'result_decorators', 'configurators'];

    private const array SINGLE = ['tool_visibility', 'prompt_visibility', 'resource_visibility'];

    private const array OPENAPI = ['operation_modifier', 'identity_provider', 'delegated_header_provider', 'handler', 'request_attributes', 'in_process_scope'];

    private const array OAUTH = ['validator', 'cache'];

    /**
     * @param array<string, mixed> $params
     *
     * @return list<string>
     */
    public static function fromParams(array $params): array
    {
        $classes = [];

        foreach (self::LISTS as $key) {
            /** @var list<string> $list */
            $list = $params[$key] ?? [];
            $classes = [...$classes, ...$list];
        }

        foreach ([['', self::SINGLE], ['openapi', self::OPENAPI], ['oauth', self::OAUTH]] as [$block, $keys]) {
            /** @var array<string, array|scalar|null> $values */
            $values = $block === '' ? $params : ($params[$block] ?? []);

            foreach ($keys as $key) {
                $class = $values[$key] ?? '';

                if (is_string($class) && $class !== '') {
                    $classes[] = $class;
                }
            }
        }

        return array_values(array_unique($classes));
    }
}
