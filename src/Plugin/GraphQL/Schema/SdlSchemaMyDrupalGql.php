<?php

namespace Drupal\mydrupalgql\Plugin\GraphQL\Schema;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\graphql\GraphQL\ResolverBuilder;
use Drupal\graphql\GraphQL\ResolverRegistry;
use Drupal\graphql\Plugin\GraphQL\Schema\SdlSchemaPluginBase;

/**
 * @Schema(
 *   id = "mydrupalgql",
 *   name = "My Drupal Graphql schema"
 * )
 * @codeCoverageIgnore
 */
class SdlSchemaMyDrupalGql extends SdlSchemaPluginBase {

  /**
   * {@inheritdoc}
   */
  protected function getSchemaDefinition() {
    return <<<GQL
      schema {
        query: Query
        mutation: Mutation
      }
      
      type Mutation {
          createArticle(data: ArticleInput): Article
      }

      type Query {
        article(id: Int!): Article
        page(id: Int!): Page
        node(id: Int!): NodeInterface
        label(id: Int!): String
        menu(name: String!): Menu
        route(path: String!): NodeInterface
      }

      type Article implements NodeInterface {
        id: Int!
        uid: String
        title: String!
        render: String
        creator: String
        tags: [TagTerm]
      }
      
      type TagTerm {
        id: Int
        name: String
      }

      type Page implements NodeInterface {
        id: Int!
        uid: String
        title: String
      }

      interface NodeInterface {
        id: Int!
      }
      
      type Menu {
        name: String!
        items: [MenuItem]
      }
      
      type MenuItem {
        title: String!
        url: Url!
        children: [MenuItem]
      }
      
      type Url {
        path: String
      }
      
      input ArticleInput {
          title: String!
          description: String
      }
GQL;
  }

  /**
   * {@inheritdoc}
   */
  protected function getResolverRegistry() {
    $builder = new ResolverBuilder();
    $registry = new ResolverRegistry([
      'Article' => ContextDefinition::create('entity:node')
        ->addConstraint('Bundle', 'article'),
      'Page' => ContextDefinition::create('entity:node')
        ->addConstraint('Bundle', 'page'),
    ]);

    $registry->addFieldResolver('Query', 'node',
      $builder->produce('entity_load', ['mapping' => [
        'entity_type' => $builder->fromValue('node'),
        'entity_id' => $builder->fromArgument('id'),
      ]])
    );

    $registry->addFieldResolver('Query', 'label',
      $builder->produce('entity_label', ['mapping' => [
        'entity' => $builder->produce('entity_load', ['mapping' => [
          'entity_type' => $builder->fromValue('node'),
          'entity_bundle' => $builder->fromValue(['article']),
          'entity_id' => $builder->fromArgument('id'),
        ]])
      ]])
    );

    $registry->addFieldResolver('Query', 'article',
      $builder->produce('entity_load', ['mapping' => [
        'type' => $builder->fromValue('node'),
        'bundles' => $builder->fromValue(['article']),
        'id' => $builder->fromArgument('id'),
      ]])
    );

    $registry->addFieldResolver('Query', 'page',
      $builder->produce('entity_load', ['mapping' => [
        'entity_type' => $builder->fromValue('node'),
        'entity_bundle' => $builder->fromValue(['page']),
        'entity_id' => $builder->fromArgument('id'),
      ]])
    );

    $registry->addFieldResolver('Article', 'id',
      $builder->produce('entity_id', ['mapping' => [
        'entity' => $builder->fromParent(),
      ]])
    );

    $registry->addFieldResolver('Article', 'title',
      $builder->produce('entity_label', ['mapping' => [
        'entity' => $builder->fromParent(),
      ]])
    );

    $registry->addFieldResolver('Article', 'creator',
      $builder->produce('property_path', [
        'mapping' => [
          'type' => $builder->fromValue('entity:node'),
          'value' => $builder->fromParent(),
          'path' => $builder->fromValue('field_article_creator.value'),
        ],
      ])
    );

    $registry->addFieldResolver('Article', 'render',
      $builder->produce('entity_rendered', ['mapping' => [
        'entity' => $builder->fromParent(),
        'mode' => $builder->fromValue('full')
      ]])
    );

    $registry->addFieldResolver('Article', 'tags',
      $builder->produce('entity_reference', [
        'mapping' => [
          'entity' => $builder->fromParent(),
          'field' => $builder->fromValue('field_tags'),
        ],
      ])
    );

    $registry->addFieldResolver('TagTerm', 'id',
      $builder->produce('entity_id', ['mapping' => [
        'entity' => $builder->fromParent(),
      ]])
    );

    $registry->addFieldResolver('TagTerm', 'name',
      $builder->produce('entity_label', ['mapping' => [
        'entity' => $builder->fromParent(),
      ]])
    );

    $registry->addFieldResolver('Page', 'id',
      $builder->produce('entity_id', ['mapping' => [
        'entity' => $builder->fromParent(),
      ]])
    );

    // Menu query.
    $registry->addFieldResolver('Query', 'menu',
      $builder->produce('entity_load', [
        'mapping' => [
          'type' => $builder->fromValue('menu'),
          'id' => $builder->fromArgument('name'),
        ],
      ])
    );

    // Menu name.
    $registry->addFieldResolver('Menu', 'name',
      $builder->produce('property_path', [
        'mapping' => [
          'type' => $builder->fromValue('entity:menu'),
          'value' => $builder->fromParent(),
          'path' => $builder->fromValue('label'),
        ],
      ])
    );

    // Menu items.
    $registry->addFieldResolver('Menu', 'items',
      $builder->produce('menu_links', [
        'mapping' => [
          'menu' => $builder->fromParent(),
        ],
      ])
    );

    // Menu title.
    $registry->addFieldResolver('MenuItem', 'title',
      $builder->produce('menu_link_label', [
        'mapping' => [
          'link' => $builder->produce('menu_tree_link', [
            'mapping' => [
              'element' => $builder->fromParent(),
            ],
          ]),
        ],
      ])
    );

    // Menu children.
    $registry->addFieldResolver('MenuItem', 'children',
      $builder->produce('menu_tree_subtree', [
        'mapping' => [
          'element' => $builder->fromParent(),
        ],
      ])
    );

    // Menu url.
    $registry->addFieldResolver('MenuItem', 'url',
      $builder->produce('menu_link_url', [
        'mapping' => [
          'link' => $builder->produce('menu_tree_link', [
            'mapping' => [
              'element' => $builder->fromParent(),
            ],
          ]),
        ],
      ])
    );

    $registry->addFieldResolver('Url', 'path',
      $builder->produce('url_path', [
        'mapping' => [
          'url' => $builder->fromParent(),
        ],
      ])
    );

    $registry->addFieldResolver('Query', 'route', $builder->compose(
      $builder->produce('route_load', [
        'mapping' => [
          'path' => $builder->fromArgument('path'),
        ],
      ]),
      $builder->produce('route_entity', [
        'mapping' => [
          'url' => $builder->fromParent(),
        ],
      ])
    ));

    $registry->addFieldResolver('Mutation', 'createArticle',
      $builder->produce('create_article', [
        'mapping' => [
          'data' => $builder->fromArgument('data'),
        ],
      ])
    );

    return $registry;
  }
}
