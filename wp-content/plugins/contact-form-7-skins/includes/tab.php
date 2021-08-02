<?php
/**
 * CF7 Skins Admin Tab Class.
 * 
 * Implement Tab functionality on CF7 Skins Admin.
 * 
 * @package cf7skins
 * @author Neil Murray
 * 
 * @since 0.1.0
 */

class CF7_Skins_Admin_Tab extends CF7_Skins_Admin {
	
	// Class variables
	var $tabs, $template, $style;
	
	/**
	 * Class constructor
	 *
	 * @since 0.1.0
	 */
	function __construct() {
		parent::__construct(); // run parent class
		
		$this->template = new CF7_Skin_Template();
		$this->style = new CF7_Skin_Style();	
		
		add_action( 'wp_ajax_cf7s_sort_skin', array( &$this, 'sort_skin' ) );
		add_action( 'wp_ajax_nopriv_cf7s_sort_skin', array( &$this, 'sort_skin' ) );
		add_action( 'cf7skins_tab_content', array( &$this, 'style_content' ) ); // @since 1.2.0
		add_action( 'cf7skins_tab_content', array( &$this, 'template_content' ) ); // @since 1.2.0
	}
	
	/**
	 * Sort styles/templates based on selected filter using AJAX.
	 * 
	 * @options all, new, search, tag
	 * @output HTML
	 * 
	 * @since 0.1.0
	 */	
	function sort_skin() {
		// Check the nonce and if not isset the id, just die.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'cf7s' ) || ! isset( $_POST['tab'] ) || ! isset( $_POST['sort'] ) )
			die();
		
		if( strpos( $_POST['tab'], 'template' ) ) {	// templates
			
			$templates = $this->template->cf7s_get_template_list();
			$dates = $new_templates = array();
			
			switch ( $_POST['sort'] ) :
				
				case 'all': // display all
					$new_templates = $templates;
					break;
					
				case 'new':	// sort by date
					foreach( $templates as $key => $template ) {
						if( $template['details']['Version Date'] ) {
							$template_date = explode( "//", $template['details']['Version Date'] );
							$template_date = $template_date[0];
							$dates[$key] = mysql2date( 'U', $template_date );
						}
					}
					
					arsort( $dates );
					$dates = array_slice( $dates, 0, 10, true ); // get only the first 10
					
					foreach( $dates as $key => $date )
						$new_templates[$key] = $templates[$key];
					break;
					
				case 'search':
					if( ! isset( $_POST['keyword'] ) && empty( $_POST['keyword'] ) )
						return;
								
					$keyword = esc_attr( $_POST['keyword'] );
					foreach( $templates as $key => $template ) {
						$match = false;
						foreach( $template['details'] as $details )
							if( stristr( $details, $keyword ) !== false )
								$match = true;
						
						if( $match )
							$new_templates[$key] =  $template;
					}
					break;
					
				case 'tag':
					if( ! isset( $_POST['tags'] ) && empty( $_POST['tags'] ) ) 
						return;
					
					foreach( $templates as $key => $template ) {
						if( empty( $template['details']['Tags'] ) ) // bail early if empty
							continue;
						
						$stripped_tags = str_replace( ' ', '', $template['details']['Tags'] ); // strip all spaces before exploding
						$template_tags = explode( ',', $stripped_tags );
						$match = false;
						
						foreach( $_POST['tags'] as $tag )
							if( in_array( esc_attr( $tag ), $template_tags ) )
								$match = true;
								
						if( $match )
							$new_templates[$key] = $template;
					}
					break;
					
				default:
					foreach( $templates as $key => $template )
						if( in_array( $_POST['sort'], $template['tags'] ) )
							$new_templates[$key] = $template;
					break;
			
			endswitch;
			
			if( $new_templates )
				$this->template->templates_list( $new_templates );
			else
				echo '<p class="no-themes no-skins">'. __( 'No templates found. Try a different search.', CF7SKINS_TEXTDOMAIN ) . '</p>';
		
		} elseif( strpos( $_POST['tab'], 'style' ) ) {	// styles
			
			$styles = $this->style->cf7s_get_style_list();
			$dates = $new_styles = array();
			
			switch ( $_POST['sort'] ) :
				
				case 'all':	// display all
					$new_styles = $styles;
					break;
				
				case 'new':	// sort by date
					foreach( $styles as $key => $style ) {
						if( $style['details']['Version Date'] ) {
							$skin_date = explode( "//", $style['details']['Version Date'] );
							$skin_date = $skin_date[0];
							$dates[$key] = mysql2date( 'U', $skin_date );
						}
					}
					
					arsort( $dates );
					$dates = array_slice( $dates, 0, 10, true ); // get only the first 10
					foreach( $dates as $key => $date )
						$new_styles[$key] = $styles[$key];
					break;
					
				case 'search':
					if( ! isset( $_POST['keyword'] ) && empty( $_POST['keyword'] ) )
						return;
					
					$keyword = esc_attr( $_POST['keyword'] );
					foreach( $styles as $key => $style ) {
						$match = false;
						foreach( $style['details'] as $details )
							if( stristr( $details, $keyword ) !== false )
								$match = true;
								
						if( $match )
							$new_styles[$key] = $style;
					}
					break;
					
				case 'tag':
					if( ! isset( $_POST['tags'] ) && empty( $_POST['tags'] ) )
						return;
					
					foreach( $styles as $key => $style ) {
						if( empty( $style['details']['Tags'] ) ) // bail early if empty
							continue;
							
						$stripped_tags = str_replace( ' ', '', $style['details']['Tags'] ); // strip all spaces before exploding
						$style_tags = explode( ',', $stripped_tags );
						$match = false;
						
						foreach( $_POST['tags'] as $tag )	
							if( in_array( esc_attr( $tag ), $style_tags ) )
								$match = true;
								
						if( $match )
							$new_styles[$key] = $style;	
					}
					break;
					
				default:
					foreach( $styles as $key => $style )
						if( in_array( $_POST['sort'], $style['tags'] ) )
							$new_styles[$key] = $style;	
					break;
			
			endswitch;
			
			if( $new_styles )
				$this->style->styles_list( $new_styles );
			else
				echo '<p class="no-themes no-skins">'. __( 'No styles found. Try a different search.', CF7SKINS_TEXTDOMAIN ) . '</p>';
		}
		
		exit();
	}
	
	/**
	 * Create tabs for styles or templates.
	 * 
	 * Function called in parent class admin.php
	 * 
	 * @output HTML
	 * @filter 'cf7skins_tabs'
	 * @do_action 'cf7skins_tab_content'
	 * 
     * @param $post current post object
     * @param $box metabox arguments
	 * 
	 * @since 0.1.0
	 */
	function generate_tab( $post, $box ) {
		$option = get_option( CF7SKINS_OPTIONS );
		$color_scheme = isset( $option['color_scheme'] ) ? $option['color_scheme'] : '';
		
		$tabs = array();
		
		// Template tooltip text @since 0.2.0
		$tabs['template'] = array(
			'label' => __( 'Template', CF7SKINS_TEXTDOMAIN ),
			'note' => __( 'Templates are pre-created forms that automatically create the form’s structure and content for you. Each template works as an easy to follow guide.', CF7SKINS_TEXTDOMAIN ),
			'help' => __( 'Select a template that closely matches your needs then switch to the CF7 Skins Form  tab to add, duplicate or remove fields to match your requirements. Any field content can be changed by clicking Edit on the field.', CF7SKINS_TEXTDOMAIN ),
		);
		
		// Style tooltip text @since 0.2.0
		$tabs['style'] = array(
			'label' => __( 'Style', CF7SKINS_TEXTDOMAIN ),
			'note' => __( 'Styles are pre-created designs (CSS code) that are automatically applied to your entire form. They cover all standard Contact Form 7 elements.', CF7SKINS_TEXTDOMAIN ),
			'help' => __( 'Select a style that aligns with your site’s design. To see the style applied, save and go to the page that has the form. To change the style, select another any time.', CF7SKINS_TEXTDOMAIN )
		);
		
		// ADD EXPLANATION
		$this->tabs = apply_filters( 'cf7skins_tabs', $tabs ); // tabs filter @since 1.2.0

		// Get current post ID, check if CF7 version < 4.2 
		$post_id = isset( $post->ID ) ? $post->ID : $post->id();
		
		// Get selected template/style
		$template = get_post_meta( $post_id, 'cf7s_template', true );
		$style = get_post_meta( $post_id, 'cf7s_style', true );
		
		$template_name = $style_name = ''; // selected template/style string
		
		// Get template name
		if ( $template ) {
			$templates = CF7_Skin_Template::cf7s_get_template_list();			
			$template_name = isset( $templates[$template] ) ? 
				$templates[$template]['details']['Template Name'] : 
				sprintf( __( "%s-missing", CF7SKINS_TEXTDOMAIN ), $template ) .
				'<span class="help balloon-hover balloon" title="'. __( 'Selected CF7 Skins Template is not available', CF7SKINS_TEXTDOMAIN ) .'">?</span>';
		}
		
		// Get style name
		if ( $style ) {
			$styles = CF7_Skin_Style::cf7s_get_style_list();
			$style_name = isset( $styles[$style] ) ? 
				$styles[$style]['details']['Style Name'] : 
				sprintf( __( "%s-missing", CF7SKINS_TEXTDOMAIN ), $style ) .
				'<span class="help balloon-hover balloon" title="'. __( 'Selected CF7 Skins Style is not available', CF7SKINS_TEXTDOMAIN ) .'">?</span>';
		}
		
		?>
		<div class="cf7s">
			<h2 class="nav-tab-wrapper <?php echo $color_scheme; ?>">
				<?php foreach( $this->tabs as $key => $value ) : ?>				
					<a class="nav-tab nav-tab-<?php echo $key; ?>" href="#tab-<?php echo $key; ?>">
						<?php echo $value['label']; ?>
						<span class="help balloon-hover balloon" title="<?php echo $value['note']; ?>"><?php _e( '!', CF7SKINS_TEXTDOMAIN ); ?></span>
						<span class="help balloon-hover balloon" title="<?php echo $value['help']; ?>"><?php _e( '?', CF7SKINS_TEXTDOMAIN ); ?></span>
					</a>
				<?php endforeach; ?>
				<div class="selected-skin">
					<span class="selected-template"><?php _e( 'Template', CF7SKINS_TEXTDOMAIN ); ?>: [<span><?php echo $template_name; ?></span>]</span>
					<span class="selected-style"><?php _e( 'Style', CF7SKINS_TEXTDOMAIN ); ?>: [<span><?php echo $style_name; ?></span>]</span>
				</div>
				
				<a class="nav-tab nav-tab-add-ons" href="#tab-add-ons"><?php _e( 'Add-ons', CF7SKINS_TEXTDOMAIN ); ?></a>			
				<a class="nav-tab nav-tab-getting-started" href="#tab-getting-started"><?php _e( 'Getting Started', CF7SKINS_TEXTDOMAIN ); ?></a>						
				
				<span class="ext-link">
					<?php //$this->links1(); ?>
					<?php //$this->links2(); ?>
					<?php //$this->links3(); ?>
				</span>
			</h2>
			
			<div class="nav-tab-content <?php echo $color_scheme; ?>">
				<?php foreach( $this->tabs as $key => $value ) : ?>
					<div id="tab-<?php echo $key; ?>" class="tab-content wp-clearfix">
						<?php do_action( 'cf7skins_tab_content', $key ); ?>
					</div>
				<?php endforeach; ?>
				
				<div id="tab-getting-started" class="tab-content info-tab wp-clearfix">
					<div class="info-wrapper"><?php require_once( CF7SKINS_PATH . 'includes/getting-started.php' ); ?></div>
				</div>
				<div id="tab-add-ons" class="tab-content info-tab wp-clearfix">
					<div class="info-wrapper"><?php require_once( CF7SKINS_PATH . 'includes/pro-version.php' ); ?></div>
				</div>
			</div>
		</div>
		<?php
	}
	
	/**
	 * Style Tab content.
	 * 
	 * @param $tab
	 * 
	 * @since 1.2.0
	 */
	function style_content( $tab ) {
		if ( 'style' != $tab )
			return;
		// ADD EXPLANATION
		if ( version_compare( get_bloginfo( 'version' ), '4', '<' ) )
			$this->style->cf7s_show_style_list_39();
		else
			$this->style->cf7s_show_style_list();
	}
	
	/**
	 * Template Tab content.
	 * 
	 * @param $tab
	 * 
	 * @since 1.2.0
	 */
	function template_content( $tab ) {
		if ( 'template' != $tab )
			return;
		// ADD EXPLANATION
		if ( version_compare( get_bloginfo( 'version' ), '4', '<' ) )
			$this->template->cf7s_show_template_list_39();
		else
			$this->template->cf7s_show_template_list();
	}
	
	/**
	 * Custom link 1.
	 * 
	 * @output HTML
	 * 
	 * @since 0.1.0
	 */
	function links1() { ?>
		<a href="http://docs.cf7skins.com/"><?php _e( 'Documentation', CF7SKINS_TEXTDOMAIN); ?></a>&nbsp;|&nbsp;
		<a href="http://kb.cf7skins.com/faq/"><?php _e( 'FAQ', CF7SKINS_TEXTDOMAIN); ?></a>&nbsp;|&nbsp;
		<a href="http://kb.cf7skins.com/category/tutorials/"><?php _e( 'Tutorials', CF7SKINS_TEXTDOMAIN); ?></a>&nbsp;|&nbsp;
		<a href="http://kb.cf7skins.com/"><?php _e( 'Knowledge Base', CF7SKINS_TEXTDOMAIN); ?></a>&nbsp;|&nbsp;
		<?php
	}
	
	/**
	 * Custom link 2.
	 * 
	 * @output HTML
	 * 
	 * @since 0.1.0
	 */
	function links2() { ?>
		<a href="http://cf7skins.com/support/"><?php _e( 'Support', CF7SKINS_TEXTDOMAIN ); ?></a>&nbsp;|&nbsp;
		<a href="http://cf7skins.com/blog/"><?php _e( 'Blog', CF7SKINS_TEXTDOMAIN ); ?></a>&nbsp;|&nbsp;
		<a href="http://cf7skins.com/pro-version/"><strong><?php _e( 'Pro Version', CF7SKINS_TEXTDOMAIN ); ?></strong></a>
		<?php
	}
	
	/**
	 * Custom link 3.
	 * 
	 * @output HTML
	 * 
	 * @since 0.1.0
	 */
	function links3() { ?>
		<a class="help" href="http://cf7skins.com/" title="Click a link to learn more"><?php _e( '?', CF7SKINS_TEXTDOMAIN ); ?></a>
		<?php
	}

} new CF7_Skins_Admin_Tab();
