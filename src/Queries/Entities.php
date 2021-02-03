<?php

namespace Awardis\Federation\Queries;

use App\Models\Project;
use Awardis\Federation\Resolvers\EntityResolver;

/**
 * Class Entities
 * @package Awardis\Federation\Queries
 */
class Entities {

    /**
     * @var \Awardis\Federation\Resolvers\EntityResolver
     */
    protected EntityResolver $resolver;

    /**
     * Entities constructor.
     *
     * @param \Awardis\Federation\Resolvers\EntityResolver $resolver
     */
    public function __construct(EntityResolver $resolver) {
        $this->resolver = $resolver;
    }

    /**
     * @param       $root
     * @param array $args
     * @param       $context
     * @param       $info
     *
     * @return array
     */
    public function __invoke($root, array $args, $context, $info): array {
        return collect($args['representations'])
            ->map(function ($representation) use ($root, $args, $context, $info) {
                $type = $representation['__typename'];

                if ($this->resolver->hasResolverForType($type)) {
                    $resolver = $this->resolver->getResolver($type, $representation);

                    return $resolver($root, $args, $context, $info);
                }

                return $representation;
            })
            ->all();
    }

}
