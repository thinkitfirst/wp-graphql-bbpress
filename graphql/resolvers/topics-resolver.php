<?php

function get_bur_total_count($total_count, $bur_id, $profile)
{
    global $bur_display;

    if ($profile === 'yes' && !empty($bur_display['profilehide_total_counts'])) {
        return null;
    }

    $check = get_user_meta($bur_id, 'hide_total_counts', true);
    $role = bbp_get_user_role($bur_id);
    $check2 = $bur_display[$role . 'hide_total_counts'] ?? '';

    if (!empty($bur_display['total_count']) && empty($check) && empty($check2)) {
        $label = $bur_display['total_name'] ?? 'Total';
        return $label . $total_count;
    }

    return null;
}

function resolve_topics($forumId)
{
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

function resolve_topic($id)
{
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
                'createdAt' => bbp_get_reply_post_date($reply_id),
                'authorRole' => bbp_get_reply_author_role($reply_id),
                'replyToId' => bbp_get_reply_to($reply_id),
                'authorIp' => bbp_get_author_ip($reply_id),
                'burTotal' => function () use ($reply_id) {
                    $bur_id = bbp_get_topic_author_id($reply_id);
                    $topics  = bbp_get_user_topic_count_raw($bur_id);
                    $replies = bbp_get_user_reply_count_raw($bur_id);

                    $total_count = (int) $topics + $replies;
                    return get_bur_total_count($total_count, $bur_id, 'yes');
                },
            ];
        }
        wp_reset_postdata();
    }

    return [
        'id' => bbp_get_topic_id($id),
        'title' => bbp_get_topic_title($id),
        'content' => bbp_get_topic_content($id),
        'author' => bbp_get_topic_author_link($id),
        'authorRole' => bbp_get_topic_author_role($id),
        'createdAt' => bbp_get_reply_post_date($id),
        'replies' => $replies,
        'authorIp' => bbp_get_author_ip(array('post_id' => $id)),
        'burTotal' => function () use ($id) {
            $topic_id = bbp_get_topic_id($id);
            $bur_id = bbp_get_topic_author_id($topic_id);

            $topics  = bbp_get_user_topic_count_raw($bur_id);
            $replies = bbp_get_user_reply_count_raw($bur_id);

            $total_count = (int) $topics + $replies;
            return get_bur_total_count($total_count, $bur_id, 'yes');
        }
    ];
}
