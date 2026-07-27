<?php
/**
 * Tutor Resources: downloadable files + FAQ.
 * Admins can add/edit; tutors (and admins) can view.
 */

function gtp_resources_home_url() {
    $role = $_SESSION['gtp_user']['role'] ?? '';
    if ($role === 'admin') {
        return site_url('/index.php/admin-dashboard/');
    }
    return site_url('/index.php/TA-dashboard/');
}

function gtp_resource_allowed_mimes($mimes) {
    $mimes['pdf'] = 'application/pdf';
    $mimes['doc'] = 'application/msword';
    $mimes['docx'] = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    $mimes['ppt'] = 'application/vnd.ms-powerpoint';
    $mimes['pptx'] = 'application/vnd.openxmlformats-officedocument.presentationml.presentation';
    $mimes['xls'] = 'application/vnd.ms-excel';
    $mimes['xlsx'] = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    $mimes['txt'] = 'text/plain';
    $mimes['csv'] = 'text/csv';
    $mimes['zip'] = 'application/zip';
    $mimes['png'] = 'image/png';
    $mimes['jpg|jpeg'] = 'image/jpeg';
    $mimes['gif'] = 'image/gif';
    $mimes['webp'] = 'image/webp';
    return $mimes;
}

function gtp_get_resource_files() {
    global $wpdb;
    return $wpdb->get_results(
        "SELECT * FROM {$wpdb->prefix}gtp_resource_files
         ORDER BY sort_order ASC, created_at DESC, id DESC"
    );
}

function gtp_get_resource_faqs() {
    global $wpdb;
    return $wpdb->get_results(
        "SELECT * FROM {$wpdb->prefix}gtp_resource_faq
         ORDER BY sort_order ASC, created_at ASC, id ASC"
    );
}

function gtp_handle_resource_admin_actions() {
    if (!isset($_SESSION['gtp_user']) || ($_SESSION['gtp_user']['role'] ?? '') !== 'admin') {
        return '';
    }

    global $wpdb;
    $admin_id = (int) $_SESSION['gtp_user']['id'];
    $files_table = $wpdb->prefix . 'gtp_resource_files';
    $faq_table = $wpdb->prefix . 'gtp_resource_faq';
    $now = current_time('mysql');
    $message = '';

    // ---- Files ----
    if (isset($_POST['gtp_add_resource_file']) && check_admin_referer('gtp_resources_files', 'gtp_resources_nonce')) {
        $title = sanitize_text_field(wp_unslash($_POST['resource_title'] ?? ''));
        $description = sanitize_textarea_field(wp_unslash($_POST['resource_description'] ?? ''));

        if ($title === '') {
            $message = '<p class="gtp-resources-msg is-error gtp-persist">Please enter a title for the file.</p>';
        } elseif (empty($_FILES['resource_file']['name'])) {
            $message = '<p class="gtp-resources-msg is-error gtp-persist">Please choose a file to upload.</p>';
        } else {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            add_filter('upload_mimes', 'gtp_resource_allowed_mimes');
            $upload = wp_handle_upload($_FILES['resource_file'], ['test_form' => false]);
            remove_filter('upload_mimes', 'gtp_resource_allowed_mimes');

            if (!empty($upload['error'])) {
                $message = '<p class="gtp-resources-msg is-error">' . esc_html($upload['error']) . '</p>';
            } else {
                $max_sort = (int) $wpdb->get_var("SELECT MAX(sort_order) FROM $files_table");
                $wpdb->insert($files_table, [
                    'title' => $title,
                    'description' => $description,
                    'file_url' => $upload['url'],
                    'file_name' => sanitize_file_name($_FILES['resource_file']['name']),
                    'file_type' => $upload['type'] ?? '',
                    'uploaded_by' => $admin_id,
                    'sort_order' => $max_sort + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $message = '<p class="gtp-resources-msg is-success">File added.</p>';
            }
        }
    }

    if (isset($_POST['gtp_update_resource_file']) && check_admin_referer('gtp_resources_files', 'gtp_resources_nonce')) {
        $id = (int) ($_POST['resource_id'] ?? 0);
        $title = sanitize_text_field(wp_unslash($_POST['resource_title'] ?? ''));
        $description = sanitize_textarea_field(wp_unslash($_POST['resource_description'] ?? ''));
        if ($id > 0 && $title !== '') {
            $data = [
                'title' => $title,
                'description' => $description,
                'updated_at' => $now,
            ];
            if (!empty($_FILES['resource_file']['name'])) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                add_filter('upload_mimes', 'gtp_resource_allowed_mimes');
                $upload = wp_handle_upload($_FILES['resource_file'], ['test_form' => false]);
                remove_filter('upload_mimes', 'gtp_resource_allowed_mimes');
                if (!empty($upload['error'])) {
                    $message = '<p class="gtp-resources-msg is-error">' . esc_html($upload['error']) . '</p>';
                } else {
                    $data['file_url'] = $upload['url'];
                    $data['file_name'] = sanitize_file_name($_FILES['resource_file']['name']);
                    $data['file_type'] = $upload['type'] ?? '';
                }
            }
            if ($message === '') {
                $wpdb->update($files_table, $data, ['id' => $id]);
                $message = '<p class="gtp-resources-msg is-success">File updated.</p>';
            }
        }
    }

    if (isset($_POST['gtp_delete_resource_file']) && check_admin_referer('gtp_resources_files', 'gtp_resources_nonce')) {
        $id = (int) ($_POST['resource_id'] ?? 0);
        if ($id > 0) {
            $wpdb->delete($files_table, ['id' => $id]);
            $message = '<p class="gtp-resources-msg is-success">File removed.</p>';
        }
    }

    // ---- FAQ ----
    if (isset($_POST['gtp_add_faq']) && check_admin_referer('gtp_resources_faq', 'gtp_faq_nonce')) {
        $question = sanitize_text_field(wp_unslash($_POST['faq_question'] ?? ''));
        $answer = sanitize_textarea_field(wp_unslash($_POST['faq_answer'] ?? ''));
        if ($question === '' || $answer === '') {
            $message = '<p class="gtp-resources-msg is-error gtp-persist">Question and answer are required.</p>';
        } else {
            $max_sort = (int) $wpdb->get_var("SELECT MAX(sort_order) FROM $faq_table");
            $wpdb->insert($faq_table, [
                'question' => $question,
                'answer' => $answer,
                'sort_order' => $max_sort + 1,
                'updated_by' => $admin_id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $message = '<p class="gtp-resources-msg is-success">FAQ item added.</p>';
        }
    }

    if (isset($_POST['gtp_update_faq']) && check_admin_referer('gtp_resources_faq', 'gtp_faq_nonce')) {
        $id = (int) ($_POST['faq_id'] ?? 0);
        $question = sanitize_text_field(wp_unslash($_POST['faq_question'] ?? ''));
        $answer = sanitize_textarea_field(wp_unslash($_POST['faq_answer'] ?? ''));
        if ($id > 0 && $question !== '' && $answer !== '') {
            $wpdb->update($faq_table, [
                'question' => $question,
                'answer' => $answer,
                'updated_by' => $admin_id,
                'updated_at' => $now,
            ], ['id' => $id]);
            $message = '<p class="gtp-resources-msg is-success">FAQ item updated.</p>';
        }
    }

    if (isset($_POST['gtp_delete_faq']) && check_admin_referer('gtp_resources_faq', 'gtp_faq_nonce')) {
        $id = (int) ($_POST['faq_id'] ?? 0);
        if ($id > 0) {
            $wpdb->delete($faq_table, ['id' => $id]);
            $message = '<p class="gtp-resources-msg is-success">FAQ item deleted.</p>';
        }
    }

    return $message;
}

function gtp_tutor_resources_shortcode() {
    if (!isset($_SESSION['gtp_user'])) {
        return '<p>Please log in to view tutor resources.</p>';
    }

    $role = $_SESSION['gtp_user']['role'] ?? '';
    if (!in_array($role, ['admin', 'tutor'], true)) {
        return '<p>You do not have access to this page.</p>';
    }

    $is_admin = ($role === 'admin');
    $message = $is_admin ? gtp_handle_resource_admin_actions() : '';
    $files = gtp_get_resource_files();
    $faqs = gtp_get_resource_faqs();
    $edit_file_id = ($is_admin && isset($_GET['edit_file'])) ? (int) $_GET['edit_file'] : 0;
    $edit_faq_id = ($is_admin && isset($_GET['edit_faq'])) ? (int) $_GET['edit_faq'] : 0;
    $page_url = site_url('/index.php/tutor-resources/');

    $edit_file = null;
    $edit_faq = null;
    if ($edit_file_id > 0) {
        foreach ($files as $f) {
            if ((int) $f->id === $edit_file_id) {
                $edit_file = $f;
                break;
            }
        }
    }
    if ($edit_faq_id > 0) {
        foreach ($faqs as $f) {
            if ((int) $f->id === $edit_faq_id) {
                $edit_faq = $f;
                break;
            }
        }
    }

    ob_start();
    ?>
    <div class="gtp-home gtp-resources-page">
        <?php echo gtp_dashboard_back_link(); ?>
        <h1 class="gtp-page-title">Tutor Resources</h1>
        <p class="gtp-resources-intro">Documents and frequently asked questions for GTP tutors.</p>
        <?php echo $message; ?>

        <section class="gtp-resources-section">
            <div class="gtp-resources-section-header">
                <h2>Resources</h2>
            </div>

            <?php if (empty($files)) : ?>
                <p class="gtp-resources-empty">No documents have been posted yet.</p>
            <?php else : ?>
                <ul class="gtp-resources-file-list">
                    <?php foreach ($files as $file) : ?>
                        <li class="gtp-resources-file">
                            <div class="gtp-resources-file-main">
                                <a class="gtp-resources-file-title" href="<?php echo esc_url($file->file_url); ?>" target="_blank" rel="noopener noreferrer">
                                    <?php echo esc_html($file->title); ?>
                                </a>
                                <?php if (!empty($file->description)) : ?>
                                    <p class="gtp-resources-file-desc"><?php echo esc_html($file->description); ?></p>
                                <?php endif; ?>
                                <p class="gtp-resources-file-meta">
                                    <?php echo esc_html($file->file_name ?: 'Download'); ?>
                                    · <?php echo esc_html(date('M j, Y', strtotime($file->created_at))); ?>
                                </p>
                            </div>
                            <?php if ($is_admin) : ?>
                                <div class="gtp-resources-admin-actions">
                                    <a href="<?php echo esc_url(add_query_arg('edit_file', (int) $file->id, $page_url)); ?>">Edit</a>
                                    <form method="post" onsubmit="return confirm('Remove this file from resources?');">
                                        <?php wp_nonce_field('gtp_resources_files', 'gtp_resources_nonce'); ?>
                                        <input type="hidden" name="resource_id" value="<?php echo (int) $file->id; ?>">
                                        <button type="submit" name="gtp_delete_resource_file" value="1" class="gtp-resources-link-btn">Delete</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if ($is_admin) : ?>
                <div class="gtp-resources-admin-box">
                    <h3><?php echo $edit_file ? 'Edit document' : 'Add document'; ?></h3>
                    <form method="post" enctype="multipart/form-data" class="gtp-resources-form">
                        <?php wp_nonce_field('gtp_resources_files', 'gtp_resources_nonce'); ?>
                        <?php if ($edit_file) : ?>
                            <input type="hidden" name="resource_id" value="<?php echo (int) $edit_file->id; ?>">
                        <?php endif; ?>
                        <label>
                            <span>Title</span>
                            <input type="text" name="resource_title" required value="<?php echo esc_attr($edit_file->title ?? ''); ?>">
                        </label>
                        <label>
                            <span>Description (optional)</span>
                            <textarea name="resource_description" rows="2"><?php echo esc_textarea($edit_file->description ?? ''); ?></textarea>
                        </label>
                        <label>
                            <span><?php echo $edit_file ? 'Replace file (optional)' : 'File'; ?></span>
                            <input type="file" name="resource_file" <?php echo $edit_file ? '' : 'required'; ?>>
                        </label>
                        <div class="gtp-resources-form-actions">
                            <?php if ($edit_file) : ?>
                                <button type="submit" name="gtp_update_resource_file" value="1" class="button button-primary">Save changes</button>
                                <a class="button" href="<?php echo esc_url($page_url); ?>">Cancel</a>
                            <?php else : ?>
                                <button type="submit" name="gtp_add_resource_file" value="1" class="button button-primary">Upload document</button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </section>

        <section class="gtp-resources-section">
            <div class="gtp-resources-section-header">
                <h2>FAQ</h2>
            </div>

            <?php if (empty($faqs)) : ?>
                <p class="gtp-resources-empty">No FAQ items yet.</p>
            <?php else : ?>
                <div class="gtp-resources-faq-list">
                    <?php foreach ($faqs as $faq) : ?>
                        <details class="gtp-resources-faq-item">
                            <summary><?php echo esc_html($faq->question); ?></summary>
                            <div class="gtp-resources-faq-answer"><?php echo nl2br(esc_html($faq->answer)); ?></div>
                            <?php if ($is_admin) : ?>
                                <div class="gtp-resources-admin-actions" style="padding:0 14px 12px;">
                                    <a href="<?php echo esc_url(add_query_arg('edit_faq', (int) $faq->id, $page_url)); ?>">Edit</a>
                                    <form method="post" onsubmit="return confirm('Delete this FAQ item?');">
                                        <?php wp_nonce_field('gtp_resources_faq', 'gtp_faq_nonce'); ?>
                                        <input type="hidden" name="faq_id" value="<?php echo (int) $faq->id; ?>">
                                        <button type="submit" name="gtp_delete_faq" value="1" class="gtp-resources-link-btn">Delete</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </details>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($is_admin) : ?>
                <div class="gtp-resources-admin-box">
                    <h3><?php echo $edit_faq ? 'Edit FAQ' : 'Add FAQ'; ?></h3>
                    <form method="post" class="gtp-resources-form">
                        <?php wp_nonce_field('gtp_resources_faq', 'gtp_faq_nonce'); ?>
                        <?php if ($edit_faq) : ?>
                            <input type="hidden" name="faq_id" value="<?php echo (int) $edit_faq->id; ?>">
                        <?php endif; ?>
                        <label>
                            <span>Question</span>
                            <input type="text" name="faq_question" required value="<?php echo esc_attr($edit_faq->question ?? ''); ?>">
                        </label>
                        <label>
                            <span>Answer</span>
                            <textarea name="faq_answer" rows="4" required><?php echo esc_textarea($edit_faq->answer ?? ''); ?></textarea>
                        </label>
                        <div class="gtp-resources-form-actions">
                            <?php if ($edit_faq) : ?>
                                <button type="submit" name="gtp_update_faq" value="1" class="button button-primary">Save changes</button>
                                <a class="button" href="<?php echo esc_url($page_url); ?>">Cancel</a>
                            <?php else : ?>
                                <button type="submit" name="gtp_add_faq" value="1" class="button button-primary">Add FAQ</button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </section>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('gtp_tutor_resources', 'gtp_tutor_resources_shortcode');
