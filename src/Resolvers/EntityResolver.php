<?php

namespace Awardis\Federation\Resolvers;

use Awardis\Federation\FederationException;
use GraphQL\Deferred;
use Illuminate\Support\Str;

/**
 * Class EntityResolver
 * @package Awardis\Federation\Resolvers
 */
class EntityResolver {

    /**
     * @var \Awardis\Federation\Resolvers\Buffer
     */
    protected Buffer $buffer;

    /**
     * @var array
     */
    protected array $resolvers = [];

    /**
     * EntityResolver constructor.
     *
     * @param \Awardis\Federation\Resolvers\Buffer $buffer
     */
    public function __construct(Buffer $buffer) {
        $this->buffer = $buffer;
    }

    /**
     * @param string      $type
     * @param string|null $resolver
     */
    public function registerResolver(string $type, string $resolver = null) {
        $this->resolvers[$type] = $resolver;
    }

    /**
     * @param string $type
     * @param array  $args
     *
     * @return \Closure
     * @throws \Awardis\Federation\FederationException
     */
    public function getResolver(string $type, array $args) {
        // If the resolver is undefined, try to resolve by underlying model
        if (!isset($this->resolvers[$type])) {
            $model = "App\\Models\\" . $type;

            if (!class_exists($model)) {
                throw new FederationException('Cannot find model for type "' . $type . '". Please specify a resolver in the @key directive.');
            }

            return function () use ($model, $args) {
                unset($args['__typename']);

                $this->buffer->add($model, $args);

                return new Deferred(function () use ($model, $args) {
                    $this->buffer->load($model);

                    return $this->buffer->get($model, $args);
                });
            };
        }

        $resolver   = $this->resolvers[$type];
        $resolverFn = '__invoke';

        if (Str::contains($resolver, '@')) {
            [$resolver, $resolverFn] = explode('@', $resolver);
        }

        return function ($root, $_, $context, $info) use ($resolver, $resolverFn, $args) {
            return (new $resolver)->{$resolverFn}($root, $args, $context, $info);
        };
    }

    /**
     * @param string $type
     *
     * @return bool
     */
    public function hasResolverForType(string $type) {
        return array_key_exists($type, $this->resolvers);
    }

}
