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