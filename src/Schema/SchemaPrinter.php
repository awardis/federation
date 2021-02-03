<?php

declare(strict_types=1);

namespace Awardis\Federation\Schema;

use GraphQL\Error\Error;
use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\ValueNode;
use GraphQL\Language\Printer;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Introspection;
use GraphQL\Type\Schema;
use GraphQL\Utils\AST;
use GraphQL\Utils\Utils;
use function array_filter;
use function array_keys;
use function array_map;
use function array_merge;
use function array_values;
use function count;
use function explode;
use function implode;
use function ksort;
use function mb_strlen;
use function preg_match_all;
use function sprintf;
use function str_replace;
use function strlen;
use function substr;

/**
 * Given an instance of Schema, prints it in GraphQL type language.
 */
class SchemaPrinter extends \GraphQL\Utils\SchemaPrinter {

    const FEDERATION_FIELDS = [
        '_service',
        '_entities',
    ];

    const FEDERATION_DIRECTIVES = [
        'key',
        'extends',
        'external',
        'extends',
        'requires',
        'provides',
    ];

    /**
     * directive => list of arguments
     */
    const FEDERATION_CUSTOM_DIRECTIVE_ARGS = [
        'key' => [
            'resolver',
        ],
    ];

    /**
     * @param bool[] $options
     */
    protected static function printObject(ObjectType $type, array $options) : string {
        $interfaces            = $type->getInterfaces();
        $implementedInterfaces = ! empty($interfaces) ?
            ' implements ' . implode(
                ' & ',
                array_map(
                    static function ($i) {
                        return $i->name;
                    },
                    $interfaces
                )
            ) : '';

        return self::printDescription($options, $type) . sprintf(
            "type %s%s%s {\n%s\n}",
            $type->name,
            $implementedInterfaces,
            static::printSchemaDirectives($type),
            self::printFields($options, $type)
        );
    }

    /**
     * @param bool[] $options
     */
    protected static function printFields($options, $type) : string {
        $fields = array_values($type->getFields());

        return implode(
            "\n",
            array_map(
                static function ($f, $i) use ($options) {
                    return self::printDescription($options, $f, '  ', ! $i) . '  ' .
                        $f->name . self::printArgs($options, $f->args, '  ') . ': ' .
                        (string) $f->getType() . static::printSchemaDirectives($f) . self::printDeprecated($f);
                },
                $fields,
                array_keys($fields)
            )
        );
    }

    /**
     * @param bool[] $options
     */
    protected static function printInterface(InterfaceType $type, array $options) : string {
        $interfaces            = $type->getInterfaces();
        $implementedInterfaces = count($interfaces) > 0
            ? ' implements ' . implode(
                ' & ',
                array_map(
                    static function (InterfaceType $interface) : string {
                        return $interface->name;
                    },
                    $interfaces
                )
            )
            : '';

        return self::printDescription($options, $type) . sprintf(
            "interface %s%s%s {\n%s\n}",
            $type->name,
            $implementedInterfaces,
            static::printSchemaDirectives($type),
            self::printFields($options, $type)
        );
    }

    /**
     * @param $type
     *
     * @return string
     * @throws \Exception
     */
    protected static function printSchemaDirectives($type) : string {
        if ($type->astNode === null) {
            return '';
        }

        if ($type->astNode instanceof ObjectTypeDefinitionNode) {
            $directives = $type->astNode->directives;
        } elseif ($type->astNode instanceof InterfaceTypeDefinitionNode) {
            $directives = $type->astNode->directives;
        } elseif ($type instanceof FieldDefinition || $type instanceof FieldDefinitionNode) {
            $directives = $type->astNode->directives;
        } else {
            return '';
        }

        if ($directives instanceof NodeList) {
            $count      = $directives->count();
            $directives = iterator_to_array($directives->getIterator());
        } else {
            $count = count($directives);
        }

        $directives = collect($directives)
            ->filter(function ($directive) {
                return in_array($directive->name->value, static::FEDERATION_DIRECTIVES);
            })
            ->all();

        return $count > 0 ? (' ' . implode(
                ' ',
                array_map(
                    static function ($directive) : string {
                        $directiveString = '@' . $directive->name->value;
                        if ($directive->arguments->count() > 0) {
                            $directiveString .= '(';
                            foreach ($directive->arguments as $argument) {
                                // Exclude custom args that are not part of the federation spec
                                if (in_array($argument->name->value, static::FEDERATION_CUSTOM_DIRECTIVE_ARGS[$directive->name->value] ?? [])) {
                                    continue;
                                }

                                $directiveString .= $argument->name->value . ': ';
                                $directiveString .= Printer::doPrint($argument->value);
                            }
                            $directiveString .= ')';
                        }

                        return $directiveString;
                    },
                    $directives
                )
            )) : '';
    }

    /**
     * @param Schema $schema
     * @param array  $options
     *
     * @return string
     */
    public static function printFederatedSchema(Schema $schema, array $options = []): string {
        $fields = $directives = [];
        $originalQueryType = $schema->getQueryType();

        foreach ($originalQueryType->getFields() as $key => $field) {
            if (in_array($key, static::FEDERATION_FIELDS)) {
                continue;
            }

            $fields[] = $field;
        }

        foreach ($schema->getDirectives() as $directive) {
            if (in_array($directive->name, static::FEDERATION_DIRECTIVES)) {
                continue;
            }

            $directives[] = $directive;
        }

        $queryType = new ObjectType([
            'name'       => 'Query',
            'fields'     => $fields,
            'interfaces' => $originalQueryType->getInterfaces(),
        ]);

        $newSchema = new Schema([
            'query'        => $queryType,
            'mutation'     => $schema->getMutationType(),
            'subscription' => $schema->getSubscriptionType(),
            'directives'   => $directives,
        ]);

        return self::printFilteredSchema(
            $newSchema,
            static function (Directive $type) {
                return !Directive::isSpecifiedDirective($type);
            },
            static function ($type) {
                return !Type::isBuiltInType($type);
            },
            $options
        );
    }

}
