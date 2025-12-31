<?php

add_action('graphql_register_types', function () {
    register_graphql_field('RootQuery', 'bbpressForums', [
        'type' => ['list_of' => 'Forum'],
        'description' => 'List of all bbPress forums, or subforums if forumId is provided.',
        'args' => [
            'forumId' => [
                'type' => 'ID',
                'description' => 'Optional ID to fetch subforums.',
                'default' => null,
            ],
        ],
        'resolve' => function ($source, $args) {
            if (!empty(bbp_forum_get_subforums($args['forumId'])) && $args['forumId']) {
                return resolve_forums($args['forumId']);
            } else if (bbp_get_forum_topic_count($args['forumId'], true)) {
                return resolve_topics($args);
            } else {
                return resolve_forums();
            }
        },
    ]);

    register_graphql_field('RootQuery', 'bbpressTopics', [
        'type' => 'BbPressTopicsResults',
        'description' => 'List of bbPress topics by forum ID with pagination.',
        'args' => [
            'forumId' => [
                'type' => 'ID',
                'description' => 'The ID of the forum for which topics are being fetched.',
                'required' => true,
            ],
            'offset' => [
                'type' => 'Int',
                'description' => 'Offset for pagination.',
                'defaultValue' => 0,
            ],
            'limit' => [
                'type' => 'Int',
                'description' => 'Number of topics to return.',
                'defaultValue' => 15,
            ],
        ],
        'resolve' => function ($source, $args) {
            return resolve_topics($args);
        },
    ]);

    register_graphql_field('RootQuery', 'bbpressForum', [
        'type' => 'Forum',
        'description' => 'A single bbPress forum by ID.',
        'args' => [
            'id' => [
                'type' => 'ID',
                'description' => 'The ID of the forum.',
            ],
        ],
        'resolve' => function ($source, $args) {
            return resolve_forum($args['id']);
        },
    ]);

    register_graphql_field('RootQuery', 'bbpressTopic', [
        'type' => 'Topic',
        'description' => 'A single bbPress topic by ID.',
        'args' => [
            'id' => [
                'type' => 'ID',
                'description' => 'The ID of the topic.',
                'required' => true,
            ],
        ],
        'resolve' => function ($source, $args) {
            return resolve_topic($args['id']);
        },
    ]);

    register_graphql_field('RootQuery', 'bbpressSearch', [
        'type' => 'BbPressSearchResults',
        'description' => 'Search bbPress forums, topics, and replies by a search term.',
        'args' => [
            'query' => [
                'type' => 'String',
                'description' => 'The search term to query bbPress content.',
                'required' => true,
            ],
            'offset' => [
                'type' => 'Int',
                'description' => 'Offset for pagination.',
                'defaultValue' => 0,
            ],
            'limit' => [
                'type' => 'Int',
                'description' => 'Number of results to return.',
                'defaultValue' => 15,
            ],
        ],
        'resolve' => function ($source, $args) {
            // $default_post_types = bbp_get_post_types(); // We may handle replies later

            $query_args = [
                'post_type'                 => ['forum', 'topic'],
                'posts_per_page'            => $args['limit'],
                's'                         => $args['query'],
                'orderby'                   => 'date',
                'order'                     => 'DESC',
                'ignore_sticky_posts'       => true,
                'perm'                      => 'readable',
                'update_post_family_cache'  => true,
                'offset'                    => $args['offset']
            ];

            $bbp = bbpress();
            $bbp->search_query = new WP_Query($query_args);

            $results = [];

            if ($bbp->search_query->have_posts()) {
                while ($bbp->search_query->have_posts()) {
                    $bbp->search_query->the_post();

                    $id = get_the_ID();
                    $subforumId = bbp_get_topic_forum_id($id);

                    $results[] = [
                        'id'                => bbp_get_forum_id($id),
                        'title'             => bbp_get_forum_title($id),
                        'content'           => bbp_get_forum_content($id),
                        'type'              => get_post_type(),
                        'topicCount'        => bbp_get_forum_topic_count($id),
                        'postCount'         => bbp_get_forum_post_count($id),
                        'freshnessLink'     => bbp_get_forum_freshness_link($id),
                        'freshnessAuthor'   => bbp_get_author_link([
                            'post_id'       => bbp_get_forum_last_active_id($id),
                            'size'          => 14,
                        ]),
                        'voicesCount'       => bbp_get_topic_voice_count($id, true),
                        'createdAt'         => get_the_date(),
                        'forumId'           => bbp_get_forum_parent_id($subforumId),
                        'subforumId'        => $subforumId,
                    ];
                }
                wp_reset_postdata();
            }

            return [
                'results' => $results,
                'hasMore' => $bbp->search_query->found_posts > ($args['offset'] + count($results)),
            ];
        },
    ]);

    // Mutations
    $handle_tags = function ($id, $tags, $is_reply = false) {
        if (
            bbp_allow_topic_tags()
            && !empty($tags)
        ) {
            $raw_tags = explode(',', $tags);

            $parsed_tags = [];

            foreach ($raw_tags as $tag) {
                $tag = sanitize_text_field(trim($tag));
                if ($tag !== '') {
                    $parsed_tags[] = $tag;
                }
            }

            if($is_reply) {
                $id = bbp_get_reply_topic_id($id);
            }

            wp_set_object_terms(
                $id,
                array_values(array_unique($parsed_tags)),
                bbp_get_topic_tag_tax_id(),
                false
            );
        }
    };

    register_graphql_mutation('createBbpressTopic', [
        'inputFields' => [
            'forumId' => [
                'type' => ['non_null' => 'ID'],
                'description' => 'The ID of the forum to post the topic in.',
            ],
            'title' => [
                'type' => ['non_null' => 'String'],
                'description' => 'The title of the topic.',
            ],
            'content' => [
                'type' => ['non_null' => 'String'],
                'description' => 'The content of the topic.',
            ],
            'tags' => [
                'type' => 'String',
                'description' => 'Comma-separated topic tags',
            ],
        ],
        'outputFields' => [
            'topicId' => [
                'type' => 'ID',
                'description' => 'The ID of the created topic.',
            ],
            'status' => [
                'type' => 'String',
                'description' => 'Result status.',
            ],
        ],
        'mutateAndGetPayload' => function ($input) use ($handle_tags) {
            if (!bbp_current_user_can_access_create_topic_form()) {
                return [
                    'topicId' => null,
                    'status' => 'error: You do not have permission to create a topic.',
                ];
            }

            $forum_id = absint($input['forumId']);
            $title = sanitize_text_field($input['title']);
            $content = sanitize_text_field($input['content']);
            $user_id = get_current_user_id();
            $tags = isset($input['tags']) ? sanitize_text_field($input['tags']) : '';

            $topic_data = [
                'post_title'    => $title,
                'post_content'  => $content,
                'post_status'   => bbp_get_public_status_id(),
                'post_author'   => $user_id,
                'post_parent'   => $forum_id,
            ];

            $topic_id = bbp_insert_topic($topic_data, $forum_id);

            if (is_wp_error($topic_id)) {
                return [
                    'topicId' => null,
                    'status' => 'error: ' . $topic_id->get_error_message(),
                ];
            }

            $handle_tags($topic_id, $tags);

            return [
                'topicId' => $topic_id,
                'status' => 'success',
            ];
        },
    ]);

    register_graphql_mutation('createBbpressReply', [
        'inputFields' => [
            'topicId' => [
                'type' => ['non_null' => 'ID'],
                'description' => 'The ID of the topic to reply to.',
            ],
            'content' => [
                'type' => ['non_null' => 'String'],
                'description' => 'The content of the reply.',
            ],
        ],
        'outputFields' => [
            'replyId' => [
                'type' => 'ID',
                'description' => 'The ID of the created reply.',
            ],
            'status' => [
                'type' => 'String',
                'description' => 'Result status.',
            ],
        ],
        'mutateAndGetPayload' => function ($input) {
            $topic_id = absint($input['topicId']);
            $content = apply_filters('bbp_new_reply_pre_content', $input['content']);

            if (empty($content)) {
                return [
                    'replyId' => null,
                    'status' => 'Content cannot be empty.',
                ];
            }

            $reply_data = [
                'post_parent'   => $topic_id,
                'post_content'  => $content,
                'post_status'   => bbp_get_public_status_id(),
                'post_author'   => get_current_user_id(),
            ];

            $reply_id = bbp_insert_reply($reply_data, $topic_id);

            if (is_wp_error($reply_id)) {
                return [
                    'replyId' => null,
                    'status' => 'error: ' . $reply_id->get_error_message(),
                ];
            }

            return [
                'replyId' => $reply_id,
                'status' => 'success',
            ];
        },
    ]);

    register_graphql_mutation('createBbpressFavorite', [
        'inputFields' => [
            'topicId' => [
                'type' => 'ID',
                'description' => 'The ID of the topic to favorite.',
                'required' => true,
            ],
        ],
        'outputFields' => [
            'success' => [
                'type' => 'Boolean',
                'description' => 'Whether the favorite was successfully added.',
            ],
            'message' => [
                'type' => 'String',
                'description' => 'A status message.',
            ],
        ],
        'mutateAndGetPayload' => function ($input) {
            $topic_id = absint($input['topicId']);
            $user_id = get_current_user_id();

            if (!bbp_is_user_favorite($user_id, $topic_id)) {
                bbp_add_user_favorite($user_id, $topic_id);
                return [
                    'success' => true,
                    'message' => 'Topic added to favorites.',
                ];
            }

            return [
                'success' => false,
                'message' => 'Topic is already a favorite.',
            ];
        },
    ]);

    register_graphql_mutation('deleteBbpressFavorite', [
        'inputFields' => [
            'topicId' => [
                'type' => 'ID',
                'description' => 'The ID of the topic to unfavorite.',
                'required' => true,
            ],
        ],
        'outputFields' => [
            'success' => [
                'type' => 'Boolean',
                'description' => 'Whether the favorite was successfully removed.',
            ],
            'message' => [
                'type' => 'String',
                'description' => 'A status message.',
            ],
        ],
        'mutateAndGetPayload' => function ($input) {
            $topic_id = absint($input['topicId']);
            $user_id = get_current_user_id();

            if (bbp_is_user_favorite($user_id, $topic_id)) {
                bbp_remove_user_favorite($user_id, $topic_id);
                return [
                    'success' => true,
                    'message' => 'Topic removed from favorites.',
                ];
            }

            return [
                'success' => false,
                'message' => 'Topic is not a favorite.',
            ];
        },
    ]);

    register_graphql_mutation('updateBbpressReply', [
        'inputFields' => [
            'replyId' => [
                'type' => ['non_null' => 'ID'],
                'description' => 'The ID of the reply to update.',
                'required' => true,
            ],
            'content' => [
                'type' => ['non_null' => 'String'],
                'description' => 'The new content for the reply.',
                'required' => true,
            ],
            'tags' => [
                'type' => 'String',
                'description' => 'Comma-separated topic tags',
            ],
        ],
        'outputFields' => [
            'success' => [
                'type' => 'Boolean',
                'description' => 'Whether the reply was successfully updated.',
            ],
            'message' => [
                'type' => 'String',
                'description' => 'A status message.',
            ],
        ],
        'mutateAndGetPayload' => function ($input) use ($handle_tags) {
            $reply_id = absint($input['replyId']);
            $content = apply_filters('bbp_edit_reply_pre_content', $input['content'], $reply_id);
            $tags = isset($input['tags']) ? sanitize_text_field($input['tags']) : '';
            $user_id = get_current_user_id();

            if (empty($content)) {
                return [
                    'success' => false,
                    'message' => 'Content cannot be empty.',
                ];
            }

            $reply = get_post($reply_id);

            if (!$reply || $reply->post_type !== bbp_get_reply_post_type()) {
                return [
                    'success' => false,
                    'message' => 'Reply not found.',
                ];
            }

            if ((int) $reply->post_author !== $user_id && !current_user_can('edit_others_replies')) {
                return [
                    'success' => false,
                    'message' => 'You do not have permission to edit this reply.',
                ];
            }

            $update = wp_update_post([
                'ID'           => $reply_id,
                'post_content' => $content,
            ], true);

            if (is_wp_error($update)) {
                return [
                    'success' => false,
                    'message' => 'Failed to update reply: ' . $update->get_error_message(),
                ];
            }

            $handle_tags($reply_id, $tags, true);

            return [
                'success' => true,
                'message' => 'Reply updated successfully.',
            ];
        },
    ]);

    register_graphql_mutation('deleteBbpressReply', [
        'inputFields' => [
            'replyId' => [
                'type' => 'ID',
                'description' => 'The ID of the reply to delete.',
                'required' => true,
            ],
        ],
        'outputFields' => [
            'success' => [
                'type' => 'Boolean',
                'description' => 'Whether the reply was successfully deleted.',
            ],
            'message' => [
                'type' => 'String',
                'description' => 'A status message.',
            ],
        ],
        'mutateAndGetPayload' => function ($input) {
            $reply_id = absint($input['replyId']);
            $user_id = get_current_user_id();

            $reply = get_post($reply_id);

            if (!$reply || $reply->post_type !== bbp_get_reply_post_type()) {
                return [
                    'success' => false,
                    'message' => 'Reply not found.',
                ];
            }

            if ((int) $reply->post_author !== $user_id && !current_user_can('delete_others_replies')) {
                return [
                    'success' => false,
                    'message' => 'You do not have permission to delete this reply.',
                ];
            }

            $deleted = wp_delete_post($reply_id, true);

            if (!$deleted) {
                return [
                    'success' => false,
                    'message' => 'Failed to delete reply.',
                ];
            }

            return [
                'success' => true,
                'message' => 'Reply deleted successfully.',
            ];
        },
    ]);

    register_graphql_mutation('deleteBbpressTopic', [
        'inputFields' => [
            'topicId' => [
                'type' => 'ID',
                'description' => 'The ID of the topic to delete.',
                'required' => true,
            ],
        ],
        'outputFields' => [
            'success' => [
                'type' => 'Boolean',
                'description' => 'Whether the topic was successfully deleted.',
            ],
            'message' => [
                'type' => 'String',
                'description' => 'A status message.',
            ],
        ],
        'mutateAndGetPayload' => function ($input) {
            $topic_id = absint($input['topicId']);
            $user_id = get_current_user_id();

            $topic = get_post($topic_id);

            if (!$topic || $topic->post_type !== bbp_get_topic_post_type()) {
                return [
                    'success' => false,
                    'message' => 'Topic not found.',
                ];
            }

            if ((int) $topic->post_author !== $user_id && !current_user_can('delete_others_topics')) {
                return [
                    'success' => false,
                    'message' => 'You do not have permission to delete this topic.',
                ];
            }

            $deleted = wp_delete_post($topic_id, true);

            if (!$deleted) {
                return [
                    'success' => false,
                    'message' => 'Failed to delete topic.',
                ];
            }

            return [
                'success' => true,
                'message' => 'Topic deleted successfully.',
            ];
        },
    ]);

    register_graphql_mutation('updateBbpressTopic', [
        'inputFields' => [
            'topicId' => [
                'type' => ['non_null' => 'ID'],
                'description' => 'The ID of the topic to update.',
                'required' => true,
            ],
            'title' => [
                'type' => ['non_null' => 'String'],
                'description' => 'The new title for the topic.',
                'required' => true,
            ],
            'content' => [
                'type' => ['non_null' => 'String'],
                'description' => 'The new content for the topic.',
                'required' => true,
            ],
            'tags' => [
                'type' => 'String',
                'description' => 'Comma-separated topic tags',
            ],
        ],
        'outputFields' => [
            'success' => [
                'type' => 'Boolean',
                'description' => 'Whether the topic was successfully updated.',
            ],
            'message' => [
                'type' => 'String',
                'description' => 'A status message.',
            ],
        ],
        'mutateAndGetPayload' => function ($input) use ($handle_tags) {
            $topic_id = absint($input['topicId']);
            $content = apply_filters('bbp_edit_topic_pre_content', $input['content'], $topic_id);
            $tags = isset($input['tags']) ? sanitize_text_field($input['tags']) : '';
            $user_id = get_current_user_id();

            if (empty($content)) {
                return [
                    'success' => false,
                    'message' => 'Content cannot be empty.',
                ];
            }

            $topic = get_post($topic_id);

            if (!$topic || $topic->post_type !== bbp_get_topic_post_type()) {
                return [
                    'success' => false,
                    'message' => 'Topic not found.',
                ];
            }

            if ((int) $topic->post_author !== $user_id && !current_user_can('edit_others_topics')) {
                return [
                    'success' => false,
                    'message' => 'You do not have permission to edit this topic.',
                ];
            }

            $update = wp_update_post([
                'ID'           => $topic_id,
                'post_title'   => $input['title'],
                'post_content' => $content,
            ], true);

            if (is_wp_error($update)) {
                return [
                    'success' => false,
                    'message' => 'Failed to update topic: ' . $update->get_error_message(),
                ];
            }

            $handle_tags($topic_id, $tags);

            return [
                'success' => true,
                'message' => 'Topic updated successfully.',
            ];
        },
    ]);

    $get_user_subscription_ids = function (int $user_id, string $post_type): array {
        if (empty($user_id) || empty($post_type) || !bbp_is_subscriptions_active()) {
            return [];
        }

        $engagements = function_exists('bbp_user_engagements_interface')
            ? bbp_user_engagements_interface('_bbp_subscription', 'post')
            : null;

        if (is_object($engagements) && isset($engagements->type) && $engagements->type === 'user') {
            $option_key = (function_exists('bbp_get_forum_post_type') && bbp_get_forum_post_type() === $post_type)
                ? '_bbp_forum_subscriptions'
                : '_bbp_subscriptions';

            $raw = get_user_option($option_key, $user_id);
            return array_filter(array_map('absint', wp_parse_id_list($raw)));
        }

        if (!function_exists('bbp_get_user_object_query')) {
            return [];
        }

        $relationship_args = bbp_get_user_object_query(
            $user_id,
            'vg_graphql_subscription_ids',
            '_bbp_subscription',
            'post'
        );

        $query_args = wp_parse_args($relationship_args, [
            'post_type' => $post_type,
            'posts_per_page' => -1,
            'nopaging' => true,
            'fields' => 'ids',
            'orderby' => 'ID',
            'order' => 'ASC',
            'no_found_rows' => true,
            'suppress_filters' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'ignore_sticky_posts' => true,
            'perm' => 'readable',
        ]);

        $query = new \WP_Query($query_args);
        return array_map('absint', $query->posts);
    };

    register_graphql_field('RootQuery', 'bbPressIsSubscribed', [
        'type' => 'Boolean',
        'description' => 'Whether the current user is subscribed to the specified forum/topic.',
        'args' => [
            'objectId' => [
                'type' => ['non_null' => 'ID'],
                'description' => 'The forum/topic ID.',
            ],
        ],
        'resolve' => function ($root, $args) {
            $user_id = bbp_get_current_user_id();
            $object_id = !empty($args['objectId']) ? absint($args['objectId']) : 0;

            if (empty($user_id) || empty($object_id) || !bbp_is_subscriptions_active()) {
                return false;
            }

            return bbp_is_user_subscribed($user_id, $object_id, 'post');
        },
    ]);

    register_graphql_field('User', 'bbPressSubscribedTopicIds', [
        'type' => ['list_of' => 'Int'],
        'description' => 'Topic IDs the user is subscribed to.',
        'resolve' => function ($user) use ($get_user_subscription_ids) {
            if (!bbp_is_subscriptions_active()) {
                return [];
            }

            $user_id = isset($user->userId) ? absint($user->userId) : 0;
            if (empty($user_id)) {
                return [];
            }

            return $get_user_subscription_ids($user_id, bbp_get_topic_post_type());
        },
    ]);

    register_graphql_field('User', 'bbPressSubscribedForumIds', [
        'type' => ['list_of' => 'Int'],
        'description' => 'Forum IDs the user is subscribed to.',
        'resolve' => function ($user) use ($get_user_subscription_ids) {
            if (!bbp_is_subscriptions_active()) {
                return [];
            }

            $user_id = isset($user->userId) ? absint($user->userId) : 0;
            if (empty($user_id)) {
                return [];
            }

            return $get_user_subscription_ids($user_id, bbp_get_forum_post_type());
        },
    ]);

    register_graphql_mutation('updateBbPressSubscription', [
        'inputFields' => [
            'objectId' => [
                'type' => ['non_null' => 'ID'],
                'description' => 'The forum/topic ID to subscribe/unsubscribe.',
            ],
            'subscribe' => [
                'type' => ['non_null' => 'Boolean'],
                'description' => 'True to subscribe, false to unsubscribe.',
            ],
        ],
        'outputFields' => [
            'success' => [
                'type' => 'Boolean',
                'description' => 'True if the operation completed successfully.',
            ],
            'subscribed' => [
                'type' => 'Boolean',
                'description' => 'True if the current user is subscribed after the operation.',
            ],
            'objectId' => [
                'type' => 'ID',
                'description' => 'The forum/topic ID that was targeted.',
            ],
            'message' => [
                'type' => 'String',
                'description' => 'Optional message about the result.',
            ],
        ],
        'mutateAndGetPayload' => function ($input) {
            if (!bbp_is_subscriptions_active()) {
                throw new \GraphQL\Error\UserError('bbPress subscriptions are not enabled.');
            }

            $user_id = bbp_get_current_user_id();
            if (empty($user_id)) {
                throw new \GraphQL\Error\UserError('You must be logged in.');
            }

            if (!current_user_can('edit_user', $user_id)) {
                throw new \GraphQL\Error\UserError('You do not have permission to edit subscriptions for this user.');
            }

            $object_id = !empty($input['objectId']) ? absint($input['objectId']) : 0;
            $subscribe = (bool) $input['subscribe'];

            if (empty($object_id)) {
                throw new \GraphQL\Error\UserError('A valid forum/topic ID is required.');
            }

            $post_type = get_post_type($object_id);
            $allowed_types = array_filter([
                function_exists('bbp_get_topic_post_type') ? bbp_get_topic_post_type() : null,
                function_exists('bbp_get_forum_post_type') ? bbp_get_forum_post_type() : null,
            ]);

            if (empty($post_type) || !in_array($post_type, $allowed_types, true)) {
                throw new \GraphQL\Error\UserError('The specified object is not a bbPress forum/topic.');
            }

            $is_subscribed = bbp_is_user_subscribed($user_id, $object_id, 'post');

            if (true === $subscribe && !$is_subscribed) {
                bbp_add_user_subscription($user_id, $object_id, 'post');
            } elseif (false === $subscribe && $is_subscribed) {
                bbp_remove_user_subscription($user_id, $object_id, 'post');
            }

            $subscribed_after = bbp_is_user_subscribed($user_id, $object_id, 'post');

            return [
                'success' => ($subscribed_after === $subscribe),
                'subscribed' => $subscribed_after,
                'objectId' => $object_id,
                'message' => $subscribed_after ? 'Subscribed.' : 'Unsubscribed.',
            ];
        },
    ]);
});
