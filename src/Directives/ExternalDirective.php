<?php

namespace Awardis\Federation\Directives;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;

/**
 * Class ExternalDirective
 * @package Awardis\Federation\Directives
 */
class ExternalDirective extends BaseDirective {

    /**
     * Formal directive specification in schema definition language (SDL).
     *
     * @see http://spec.graphql.org/draft/#sec-Type-System.Directives
     *
     * This must contain a single directive definition, but can also contain
     * auxiliary types, such as enum definitions for directive arguments.
     */
    public static function definition(): string {
        return /** @lang GraphQL */ <<<SDL
        directive @external on FIELD_DEFINITION
        SDL;
    }

}
