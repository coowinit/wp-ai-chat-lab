<?php
/**
 * Manual knowledge content type used for business rules and policies that do
 * not naturally belong to the site's existing business content.
 *
 * @package WP_AI_Chat_Lab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIC_Manual_Knowledge {

	const POST_TYPE = 'wpaic_knowledge';
	const TAXONOMY  = 'wpaic_knowledge_category';

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register' ) );
	}

	/**
	 * @return void
	 */
	public function register() {
		$admin_caps = array(
			'edit_post'              => 'manage_options',
			'read_post'              => 'manage_options',
			'delete_post'            => 'manage_options',
			'edit_posts'             => 'manage_options',
			'edit_others_posts'      => 'manage_options',
			'publish_posts'          => 'manage_options',
			'read_private_posts'     => 'manage_options',
			'delete_posts'           => 'manage_options',
			'delete_private_posts'   => 'manage_options',
			'delete_published_posts' => 'manage_options',
			'delete_others_posts'    => 'manage_options',
			'edit_private_posts'     => 'manage_options',
			'edit_published_posts'   => 'manage_options',
			'create_posts'           => 'manage_options',
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels' => array(
					'name'               => 'AI 补充知识',
					'singular_name'      => 'AI 补充知识',
					'menu_name'          => 'AI 补充知识',
					'add_new'            => '添加补充知识',
					'add_new_item'       => '添加 AI 补充知识',
					'edit_item'          => '编辑 AI 补充知识',
					'new_item'           => '新建 AI 补充知识',
					'view_item'          => '查看 AI 补充知识',
					'search_items'       => '搜索 AI 补充知识',
					'not_found'          => '未找到 AI 补充知识。',
					'not_found_in_trash' => '回收站中没有 AI 补充知识。',
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => 'wp-ai-chat-lab',
				'show_in_rest'        => false,
				'query_var'           => false,
				'rewrite'             => false,
				'has_archive'         => false,
				'map_meta_cap'        => false,
				'capabilities'        => $admin_caps,
				'supports'            => array( 'title', 'editor', 'revisions' ),
				'menu_icon'           => 'dashicons-welcome-write-blog',
			)
		);

		register_taxonomy(
			self::TAXONOMY,
			array( self::POST_TYPE ),
			array(
				'labels' => array(
					'name'          => '知识分类',
					'singular_name' => '知识分类',
					'search_items'  => '搜索知识分类',
					'all_items'     => '所有知识分类',
					'edit_item'     => '编辑知识分类',
					'update_item'   => '更新知识分类',
					'add_new_item'  => '添加知识分类',
					'new_item_name' => '新知识分类名称',
					'menu_name'     => '知识分类',
				),
				'public'            => false,
				'publicly_queryable'=> false,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_rest'      => false,
				'hierarchical'      => true,
				'rewrite'           => false,
				'capabilities'      => array(
					'manage_terms' => 'manage_options',
					'edit_terms'   => 'manage_options',
					'delete_terms' => 'manage_options',
					'assign_terms' => 'manage_options',
				),
			)
		);
	}
}
