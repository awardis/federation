**Federation is now supported by Lighthouse. Please reference the [official docs](https://lighthouse-php.com/5/federation/getting-started.html).**

---

# [WIP] [Apollo Federation](https://www.apollographql.com/docs/federation/) service support for [Lighthouse](https://github.com/nuwave/lighthouse).

**Please note that we strongly advice to not use this in any production environment but rather wait for this feature to eventually become part of Lighthouse core. You can find the ongoing discussion [here](https://github.com/nuwave/lighthouse/issues/911).**

## Installation

Install via composer by adding this repo to the `repositories` array:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/awardis/federation"
    }
  ],
  "require": {
    "awardis/federation": "dev-master"
  }
}
```

Be aware that we are currently patching two files in `nuwave/lighthouse` via [cweagans/composer-patches](https://github.com/cweagans/composer-patches) to make this package work. The patch file can be found in `patches/001-include-ast-nodes.patch` and we host the file for you [here](https://static.files.award.is/patches/001-include-ast-nodes.patch).

## Usage

While usage is similar to the [official Federation documentation](https://www.apollographql.com/docs/federation/implementing-services/), there are a few things unique to this implementation and Lighthouse:

### Resolving entities

When defining a type like this,

```graphql
type Company @key(fields: "id") {
  id: ID!
  name: ID!
}
```

Apollo Gateway may try to resolve entities by the fields defined in the `@key` directive.
This package provides a simple default resolver that tries to load models based on the typename (currently only models in "App\Models" namespace will be detected).
If you need to define a custom type resolver, you can provide it in the `@key` directive as a second argument:

```graphql
type Company @key(fields: "id", resolver: "App\\GraphQL\Entities\\Company") {
  id: ID!
  name: ID!
}
```

Where `App\\GraphQL\Entities\\Company` is just a standard Lighthouse query class. The resolver argument is not spec compliant and will not be printed by the schema printer.

*This approach has to be discussed by the community and merely resembles the most naive solution we came up with.*

### Extending types defined by other services

Because Lighthouse does not allow to `extend` types it doesn't know about, 
we have to use Federations `@extends` directive and leave out GraphQLs `extend` keyword:

```graphql
# Extend 'Company' in another service
type Company @key(fields: "id") @extends {
  id: ID! @external
  bills: [Bill!]! @paginate(defaultCount: 20, builder: "...")
}
```

## Some final notes

There are still a few missing bits, especially tests and some Federation features that we didn't try yet (e.g. multiple `@key` directives). 
We hope to provide a good starting point for someone who's proficient in Lighthouses core and willing to implement this as a core feature.

