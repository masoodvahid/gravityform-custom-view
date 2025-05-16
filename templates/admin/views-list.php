<?php
/**
 * Admin template for displaying the list of custom views
 *
 * @package Gravity_Form_Custom_View
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Custom Views', 'gravity-form-custom-view'); ?></h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=gf_custom_view&action=add')); ?>" class="page-title-action"><?php _e('Add New', 'gravity-form-custom-view'); ?></a>
    <hr class="wp-header-end">
    
    <?php if (isset($_GET['deleted'])) : ?>
    <div class="notice notice-success is-dismissible">
        <p><?php _e('Custom view deleted.', 'gravity-form-custom-view'); ?></p>
    </div>
    <?php endif; ?>
    
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('ID', 'gravity-form-custom-view'); ?></th>
                <th><?php _e('View Title', 'gravity-form-custom-view'); ?></th>
                <th><?php _e('Form', 'gravity-form-custom-view'); ?></th>
                <th><?php _e('Shortcode', 'gravity-form-custom-view'); ?></th>
                <th><?php _e('Date Created', 'gravity-form-custom-view'); ?></th>
                <th><?php _e('Actions', 'gravity-form-custom-view'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($views)) : ?>
            <tr>
                <td colspan="6"><?php _e('No custom views found.', 'gravity-form-custom-view'); ?></td>
            </tr>
            <?php else : ?>
                <?php foreach ($views as $view) : ?>
                <?php 
                $form_name = '';
                foreach ($forms as $form) {
                    if ($form['id'] == $view->form_id) {
                        $form_name = $form['title'];
                        break;
                    }
                }
                ?>
                <tr>
                    <td><?php echo esc_html($view->id); ?></td>
                    <td>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=gf_custom_view&action=edit&view_id=' . $view->id)); ?>">
                            <?php echo esc_html($view->view_title); ?>
                        </a>
                    </td>
                    <td><?php echo esc_html($form_name); ?></td>
                    <td><code>[gf-custom-view id=<?php echo esc_html($view->id); ?>]</code></td>
                    <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($view->date_created))); ?></td>
                    <td>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=gf_custom_view&action=edit&view_id=' . $view->id)); ?>" class="button button-small">
                            <?php _e('Edit', 'gravity-form-custom-view'); ?>
                        </a>
                        <form method="post" style="display:inline;" onsubmit="return confirm('<?php _e('Are you sure you want to delete this view?', 'gravity-form-custom-view'); ?>')">
                            <?php wp_nonce_field('gfcv_save_view', 'gfcv_nonce'); ?>
                            <input type="hidden" name="gfcv_action" value="delete_view">
                            <input type="hidden" name="view_id" value="<?php echo esc_attr($view->id); ?>">
                            <button type="submit" class="button button-small button-link-delete">
                                <?php _e('Delete', 'gravity-form-custom-view'); ?>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>