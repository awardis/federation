<?php

namespace Awardis\Federation;

use Awardis\Federation\Directives\ExtendsDirective;
use Awardis\Federation\Directives\ExternalDirective;
use Awardis\Federation\Directives\KeyDirective;
use Awardis\Federation\Directives\ProvidesDirective;
use Awardis\Federation\Directives\RequiresDirective;
use Awardis\Federation\Resolvers\Buffer;
use Awardis\Federation\Resolvers\EntityResolver;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Language\Parser;
use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\Events\ManipulateAST;
use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;

/**
 * Class FederationServiceProvider
 * @package Awardis\Federation
 */
class FederationServiceProvider extends ServiceProvider {

    /**
     *
     */
    public function boot() {
       $this->app->singleton(
           Buffer::class,
           fn () => new Buffer()
       );
       $this->app->singleton(
           EntityResolver::class,
           fn () => new EntityResolver(app(Buffer::class))
       );
    }

    /**
     *
     */
    public function register() {
        $this->registerEventListeners();
    }

    /**
     *
     */
    protected function registerEventListeners() {
        app('events')->listen(
            RegisterDirectiveNamespaces::class,
            function (): string {
                return "Awardis\\Federation\\Directives";
            }
        );

        app('events')->listen(
            ManipulateAST::class,
            function (ManipulateAST $event) {
                $this->addScalars($event);
                $this->addEntityUnion($event);
                $this->addServiceType($event);
                $this->addDirectives($event);
                $this->addQueries($event);
            }
        );
    }

    /**
     * @param \Nuwave\Lighthouse\Events\ManipulateAST $event
     */
    protected function addDirectives(ManipulateAST $event) {
        $event->documentAST->setDirectiveDefinition(Parser::directiveDefinition(ExternalDirective::definition()));
        $event->documentAST->setDirectiveDefinition(Parser::directiveDefinition(RequiresDirective::definition()));
        $event->documentAST->setDirectiveDefinition(Parser::directiveDefinition(ProvidesDirective::definition()));
        $event->documentAST->setDirectiveDefinition(Parser::directiveDefinition(KeyDirective::definition()));
        $event->documentAST->setDirectiveDefinition(Parser::directiveDefinition(ExtendsDirective::definition()));
    }

    /**
     * @param \Nuwave\Lighthouse\Events\ManipulateAST $event
     */
    protected function addScalars(ManipulateAST $event) {
        $event->documentAST->setTypeDefinition(
            Parser::scalarTypeDefinition(
                'scalar _Any @scalar(class: "Awardis\\\\Federation\\\\Scalars\\\\Any")'
            )
        );

        $event->documentAST->setTypeDefinition(
            Parser::scalarTypeDefinition(
                'scalar _FieldSet @scalar(class: "Awardis\\\\Federation\\\\Scalars\\\\FieldSet")'
            )
        );
    }

    /**
     * @param \Nuwave\Lighthouse\Events\ManipulateAST $event
     *
     * @throws \Awardis\Federation\FederationException
     */
    protected function addEntityUnion(ManipulateAST $event) {
        $entities = $this->getEntities($event->documentAST);

        if ($entities->count() === 0) {
            throw new FederationException('There must be at least one type defining the @key directive.');
        }

        $entities = implode(' | ', $entities->toArray());

        $event->documentAST->setTypeDefinition(
            Parser::unionTypeDefinition(sprintf("union _Entity = %s", $entities))
        );
    }

    /**
     * @param \Nuwave\Lighthouse\Events\ManipulateAST $event
     */
    protected function addServiceType(ManipulateAST $event) {
        $event->documentAST->setTypeDefinition(
            Parser::objectTypeDefinition(
                /** @lang GraphQL */ <<<SDL
                type _Service {
                  sdl: String
                }
                SDL
            )
        );
    }

    /**
     * @param \Nuwave\Lighthouse\Schema\AST\DocumentAST $ast
     *
     * @return \Illuminate\Support\Collection
     */
    protected function getEntities(DocumentAST $ast): Collection {
        return collect($ast->types)
            ->filter(function (TypeDefinitionNode $type) {
                return $type instanceof ObjectTypeDefinitionNode;
            })
            ->filter(function (ObjectTypeDefinitionNode $type) {
                return collect($type->directives)
                        ->first(function (DirectiveNode $directive) {
                            return $directive->name->value === 'key';
                        }) !== null;
            })
            ->map(function (ObjectTypeDefinitionNode $type) {
                return $type->name->value;
            });
    }

    /**
     * @param \Nuwave\Lighthouse\Events\ManipulateAST $event
     */
    protected function addQueries(ManipulateAST $event) {
        /** @var \GraphQL\Language\AST\ObjectTypeDefinitionNode $queryType */
        $queryType = $event->documentAST->types['Query'];

        $queryType->fields = ASTHelper::mergeUniqueNodeList(
            $queryType->fields,
            [
                Parser::fieldDefinition(
                    '_entities(representations: [_Any!]!): [_Entity]! @field(resolver: "Awardis\\\\Federation\\\\Queries\\\\Entities")'
                ),
                Parser::fieldDefinition(
                    '_service: _Service! @field(resolver: "Awardis\\\\Federation\\\\Queries\\\\Service")'
                ),
            ]
        );
    }

}
