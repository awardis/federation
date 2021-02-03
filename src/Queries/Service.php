<?php

namespace Awardis\Federation\Queries;

use Awardis\Federation\Schema\SchemaPrinter;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Schema;
use GraphQL\Type\SchemaConfig;

/**
 * Class Service
 * @package Awardis\Federation\Queries
 */
class Service {

    /**
     * @param                                      $_
     * @param array                                $args
     * @param                                      $context
     * @param \GraphQL\Type\Definition\ResolveInfo $info
     *
     * @return array
     */
    public function __invoke($_, array $args, $context, ResolveInfo $info): array {
        $schema = $info->schema;

        return [
            'sdl' => SchemaPrinter::printFederatedSchema($schema),
        ];
    }

}
