<?php

namespace Awardis\Federation\Directives;

use Awardis\Federation\Resolvers\EntityResolver;
use GraphQL\Language\AST\TypeDefinitionNode;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\TypeManipulator;

/**
 * Class KeyDirective
 * @package Awardis\Federation\Directives
 */
class KeyDirective extends BaseDirective implements TypeManipulator {

    /**
     * @var \Awardis\Federation\Resolvers\EntityResolver
     */
    protected EntityResolver $resolver;

    /**
     * KeyDirective constructor.
     *
     * @param \Awardis\Federation\Resolvers\EntityResolver $resolver
     */
    public function __construct(EntityResolver $resolver) {
        $this->resolver = $resolver;
    }

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
        directive @key(fields: _FieldSet!, resolver: String = null) on OBJECT | INTERFACE
        SDL;
    }

    /**
     * @param \Nuwave\Lighthouse\Schema\AST\DocumentAST $documentAST
     * @param \GraphQL\Language\AST\TypeDefinitionNode  $typeDefinition
     */
    public function manipulateTypeDefinition(DocumentAST &$documentAST, TypeDefinitionNode &$typeDefinition) {
        if (!$this->isExtension($typeDefinition)) {
            $this->resolver->registerResolver(
                $typeDefinition->name->value,
                $this->directiveArgValue('resolver') ?? null
            );
        }
    }

    /**
     * Check if node is an extension node.
     *
     * @param \GraphQL\Language\AST\TypeDefinitionNode $typeDefinition
     *
     * @return bool
     */
    protected function isExtension(TypeDefinitionNode $typeDefinition) {
        return !is_null(
            collect($typeDefinition->directives)
                ->first(function ($directive) {
                    return $directive->name->value === 'extends';
                })
        );
    }

}
