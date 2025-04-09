<?php

function resolve_forums($forumId = null) {
    $forums = [];

    if ($forumId) {
        $subforums = bbp_forum_get_subforums($forumId);
        foreach ($subforums as $subforum) {
            $forums[] = [
                'id' => bbp_get_forum_id($subforum->ID),
                'title' => bbp_get_forum_title($subforum->ID),
                'content' => bbp_get_forum_content($subforum->ID),
                'topicCount' => bbp_get_forum_topic_count($subforum->ID),
                'postCount' => bbp_get_forum_post_count($subforum->ID),
                'freshnessLink' => bbp_get_forum_freshness_link($subforum->ID),
                'freshnessAuthor' => bbp_get_author_link([
                    'post_id' => bbp_get_forum_last_active_id($subforum->ID),
                    'size'    => 14,
                ]),
            ];
        }
    } else {
        if (bbp_has_forums()) {
            while (bbp_forums()) {
                bbp_the_forum();
                $forums[] = [
                    'id' => bbp_get_forum_id(),
                    'title' => bbp_get_forum_title(),
                    'content' => bbp_get_forum_content(),
                    'topicCount' => bbp_get_forum_topic_count(),
                    'postCount' => bbp_get_forum_post_count(),
                    'freshnessLink' => bbp_get_forum_freshness_link(),
                    'freshnessAuthor' => bbp_get_author_link([
                        'post_id' => bbp_get_forum_last_active_id(),
                        'size'    => 14,
                    ]),
                ];
            }
        }
    }

    return $forums;
}

function resolve_forum($id) {
    $forum = bbp_get_forum($id);
    
    if (!$forum) {
        return null;
    }

    return [
        'id' => bbp_get_forum_id($id),
        'title' => bbp_get_forum_title($id),
        'content' => bbp_get_forum_content($id),
        'topicCount' => bbp_get_forum_topic_count($id),
        'postCount' => bbp_get_forum_post_count($id),
        'freshnessLink' => bbp_get_forum_freshness_link($id),
        'freshnessAuthor' => bbp_get_author_link([
            'post_id' => bbp_get_forum_last_active_id($id),
            'size'    => 14,
        ]),
    ];
}

function resolve_topics($forumId) {
    $topics = [];

    $args = [
        'post_parent' => $forumId,
        'post_type'   => bbp_get_topic_post_type(),
        'posts_per_page' => -1,
    ];
    $query = new WP_Query($args);

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $topic_id = get_the_ID();
            
            $topics[] = [
                'id' => $topic_id,
                'title' => bbp_get_topic_title($topic_id),
                'content' => bbp_get_topic_content($topic_id),
                'postCount' => bbp_get_topic_post_count($topic_id),
                'author' => bbp_get_topic_author_link($topic_id),
                'createdAt' => get_the_date(),
                'voicesCount' => bbp_get_topic_voice_count($topic_id, true),
                'freshnessLink' => bbp_get_topic_freshness_link($topic_id),
                'freshnessAuthor' => bbp_get_topic_author_display_name($topic_id),
            ];
        }
        wp_reset_postdata();
    }

    return $topics;
}

function resolve_topic($id) {
    $topic = bbp_get_topic($id);
    
    if (!$topic) {
        return null;
    }

    $replies = [];

    $args = [
        'post_type'      => bbp_get_reply_post_type(),
        'post_parent'    => $id,
        'posts_per_page' => -1,
        'orderby'        => 'date',
        'order'          => 'ASC',
    ];
    $query = new WP_Query($args);

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $reply_id = get_the_ID();

            $replies[] = [
                'id' => $reply_id,
                'content' => bbp_get_reply_content($reply_id),
                'author' => bbp_get_reply_author_link($reply_id),
                'createdAt' => get_the_date('', $reply_id),
                'authorRole' => bbp_get_reply_author_role($reply_id),
            ];
        }
        wp_reset_postdata();
    }

    return [
        'id' => bbp_get_topic_id($id),
        'title' => bbp_get_topic_title($id),
        'content' => bbp_get_topic_content($id),
        'replies' => $replies,
    ];
}