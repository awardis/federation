<?php

namespace Awardis\Federation\Directives;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;

/**
 * Class ProvidesDirective
 * @package Awardis\Federation\Directives
 */
class ProvidesDirective extends BaseDirective {

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
        directive @provides(fields: _FieldSet!) on FIELD_DEFINITION
        SDL;
    }

}
