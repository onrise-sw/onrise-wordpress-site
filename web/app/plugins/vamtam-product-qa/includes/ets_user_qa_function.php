<?php

namespace VamtamProductQA;

if ( ! defined( 'ABSPATH' ) ) exit;


class USER_QUESTION_ANSWER
{
	public function __construct() {

		// Create the new Tabe Add Question Field
		add_filter( 'woocommerce_product_tabs',  		 	array($this, 'question_tab'));

		add_action( 'wp_ajax_ets_post_qusetion_answer',		array($this, 'question_save'));

		// Load The Q & A on click Load More Button
		add_action( 'wp_ajax_ets_product_qa_load_more',		array($this, 'load_more_qa'));

		// without login
		add_action( 'wp_ajax_nopriv_ets_product_qa_load_more',		array($this, 'load_more_qa'));

		//variable Creation js
		add_action( 'wp_enqueue_scripts',array($this, 'qa_plugin_script' ));

		//Add CSS file
		add_action( 'wp_enqueue_scripts',array($this, 'qa_plugin_style'));

		//SMTP mail Hook
		//add_action('phpmailer_init',array($this, 'configure_smtp') );

		//Mail Content Type Html
		add_filter( 'wp_mail_content_type',array($this, 'set_html_mail_contente_type'));


	}

	/**
	* Create the new Tab Add Question Field
	*/
	public function question_tab( $tabs ) {
		$q_count = count( self::get_questions() );

		$tabs['ask'] = array(
			'title'     => $q_count > 0 ? sprintf( esc_html__( 'Q & A (%d)','vamtam-product-qa' ), $q_count ) : esc_html__( 'Q & A','vamtam-product-qa' ),
			'priority'  => 50,
			'callback'  => array($this , 'ets_ask_qustion_tab')
		);

		return $tabs;
	}

	/**
	* Save The post Question.
	*/
	public function question_save(){
		if(!wp_verify_nonce($_POST['add_qustion_nonce'],'ets-product-add-new-question')){

			$response = array(
				'status' => 0,
				'message'	=> __('Access not allowed','vamtam-product-qa').'.'
			);

			echo json_encode($response);
			die;
		}
		if ( !is_user_logged_in() ) {
			echo json_encode( array(
						'status' => 0,
						'message'	=> __('You are not logged in','vamtam-product-qa').'.'
					)
			);
			die;
		}

		$current_user = wp_get_current_user();
		$productId = intval($_POST['product_id']);
		$current_url = get_permalink( $productId );
		$userProfileUrl = get_author_posts_url($current_user);
		$userEmail = $current_user->user_email;
		$admin_email = get_option('admin_email');
		$question = sanitize_textarea_field($_POST['question']);
		$etsCustomerId = $current_user->ID;
		$etsCustomerEmail = $current_user->user_email;
		$etsCustomerName = $current_user->user_login;
		$productTitle = sanitize_text_field($_POST['ets_Product_Title']);
		$date = date("d-M-Y");
		if(!empty($question)){
			$etsUserQusetion =  array(
				'question' 			=> $question,
				'answer'			=> '',
				'user_name' 		=> $etsCustomerName,
				'user_email' 		=> $etsCustomerEmail,
				'product_title' 	=> $productTitle,
				'user_id' 			=> $etsCustomerId,
				'date'				=> $date,
			);

			$etsBlankArray = array();
			$etsGetQuestion = get_post_meta( $productId, 'ets_question_answer', true );

			if(!empty($etsGetQuestion)){
				array_push( $etsGetQuestion, $etsUserQusetion);
				$result = update_post_meta($productId, 'ets_question_answer', $etsGetQuestion);
			} else{
				array_push( $etsBlankArray, $etsUserQusetion );
				$result = update_post_meta( $productId, 'ets_question_answer', $etsBlankArray);
			}
		}

		if( isset($result) ){
			//send email notification to admin
			$response = array(
				'status' 		=> 1,
				'productId' 	=> $productId,
				'message' 		=> __("Question submitted successfully",'vamtam-product-qa').'.',
				'ets_get_question_data'	=> $result
			);
			echo json_encode($response);


		} else {

			$response = array(
				'status' => 0,
				"message"	=> __("Please enter your question", 'vamtam-product-qa').'.',
			);
			echo json_encode($response);

		}

		if( isset($result) ) {
			try{
				$message = "<a href='$userProfileUrl'>" . $etsCustomerName . "</a> added a question on the <a href='$current_url'> " . $productTitle."</a>:  <br><div style='background-color: #FFF8DC;border-left: 2px solid #ffeb8e;padding: 10px;margin-top:10px;'>". $question."</div>";
				$to = $admin_email;
				$subject = "New Question: " . get_bloginfo('name');
				wp_mail($to, $subject, $message);
			}
			catch(Exception $e)
			{

			}

		}
		die();
	}

	/**
	* Question Mail Html
	*/
	public function set_html_mail_contente_type() {
		return "text/html";
	}

	/**
	 * Get all questions
	 * @return array
	 */
	public static function get_questions() {
		global $product;

		if ( ! $product ) {
			return [];
		}

		$productId = $product->get_id();

		return get_post_meta( $productId,'ets_question_answer', true ) ?: [];
	}

	/**
	*Create Text Area and Ask button
	*/
	public function ets_ask_qustion_tab() {
		global $product;
		$productId = $product->get_id();
		$productTitle = get_the_title($productId);
		$user = wp_get_current_user();
		$productQaLength = get_option('ets_product_q_qa_list_length');
		$current_user = $user->exists();
		$site_url = get_site_url();

		if( $current_user == true ){
			$uesrName = $user->user_login;
			$userId = $user->ID;
			$uesrEmail = $user->user_email;
			?>
			<form action="#" method="post"  id="ets-qus-form" name="form">
				<textarea id="ques-text-ar" cols="45" rows="3" id="name" class="ets-qa-textarea"   name="question" value="" placeholder="<?php echo __('Enter your question here','vamtam-product-qa') ?>..." height= "75px" ></textarea>
				<input type="hidden" id="useremail" class="productId" name="usermail" value="<?php echo $uesrEmail ?>">
				<input type="hidden" id="custId" class="productId" name="product_id" value="<?php echo $productId ?>">
				<input type="hidden" id="productlength" class="productlength" name="Product_Qa_Length" value="<?php echo $productQaLength ?>">
				<input type="hidden" id="producttitle" name="ets_Product_Title" value="<?php echo $productTitle ?>">
				<div class="ets-display-message"><p></p></div>
				<div class="ets-dis-message-error"><p></p></div>
				<button id="ets-submit" type="submit" name="submit" class="btn btn-info" ><?php echo __('Submit','vamtam-product-qa'); ?></button>
			</form>
			<div id="ets_product_qa_length"><p></p></div>
			<?php
		} else { ?>

			<form action="#" method="post"  id="ets-qus-form" name="form">
				<input type="hidden" id="custId" class="productId" name="product_id" value="<?php echo $productId ?>">
				<input type="hidden" id="productlength" class="productlength" name="Product_Qa_Length" value="<?php echo $productQaLength ?>">
				<input type="hidden" id="producttitle" name="ets_Product_Title" value="<?php echo $productTitle ?>">
			</form>
			<div id="ets_product_qa_length"><p></p></div>
			<a href="<?php echo $site_url.'/wp-login.php' ?>" class="ets-load-more">
			<?php echo __('Please login to post questions', 'vamtam-product-qa');?>.
			</a>
			<?php
			}
			$loadMoreButtonName = get_option('ets_load_more_button_name');
			$productQaLength = get_option('ets_product_q_qa_list_length');
			$loadMoreButton = get_option('ets_load_more_button');
			$pagingType = get_option('ets_product_qa_paging_type' );

			$etsGetQuestion = self::get_questions();

			if(!empty($etsGetQuestion)){
				end( $etsGetQuestion);
				$keyData =  max(array_keys($etsGetQuestion));
			}

			if($loadMoreButton == 1) {
				if(empty($loadMoreButtonName)){
					$loadMoreButtonName = __("Load More",'vamtam-product-qa');
					update_option( 'ets_load_more_button_name', $loadMoreButtonName );
				}
				if(!empty($etsGetQuestion)){

					$count = 1;
					if (empty($productQaLength)) {
						$productQaLength = 4;
					}


					if($pagingType == 'accordion') :
						?>
						<div class='ets-qa-listing'>
						<?php
						foreach ($etsGetQuestion as $key => $value) {
							?>
							<div class="ets-accordion">
								<span class="que-content"><b><?php echo __('Question','vamtam-product-qa') ?>:</b></span>
								<span class="que-content-des"><?php echo $value['question'];?></span>
								<h6><?php echo $value['user_name']. "<br>";?><?php echo $value['date']; ?></h6>
							</div>
							<div class="ets-panel">
								<?php
								if(!empty($value['answer'])){?>
									<span class="ans-content"><b><?php echo __('Answer','vamtam-product-qa') ?>:</b>
									</span>
									<span class="ans-content-des"><?php echo $value['answer'];?>
									</span>

							<?php
								} else { ?>
								<span class="ans-content"><b><?php echo __('Answer','vamtam-product-qa') ?>.</b></span>
								<span class="ans-content-des"><i><?php echo __("Answer awaiting",'vamtam-product-qa');?>...</i>
								</span>
								<?php
							}?>
							</div>

							<?php
							$count++;
							if($count > $productQaLength){
								break;
							}
						}
						?>
						<div class='ets-accordion-response-add'></div>
						</div>
						<?php
					else :

						?>
						<div class="table-responsive my-table">
						<table class="table table-striped">
						<tbody class="table1">
						<?php
						//Show Question Answer Listing Type Table With Load More
						foreach ($etsGetQuestion as $key => $value) {
							?>
							<tr class="ets-question-top">
								<td class="ets-question-title"><p><?php echo __('Question','vamtam-product-qa'); ?>:</p></td>
								<td class="ets-question-description"><p><?php echo $value['question'];?></p></td>
								<td class="ets-cont-right"><h6 class="user-name"><?php echo $value['user_name'] . "<br>";
								echo ($value['date']); ?></h6></td>
							</tr>
							<?php
							if(!empty($value['answer'])){
							?>
								<tr>
									<td class="ets-question-title"><p><?php echo __('Answer','vamtam-product-qa'); ?>:</p></td>
									<td colspan="2"><p> <?php echo $value['answer'];?></p></td>
								</tr>
							<?php
							} else {
							?>
								<tr>
									<td class="ets-question-title"><p><?php echo __('Answer:','vamtam-product-qa'); ?></p></td>
									<td colspan="2" class="ets-no-answer" ><h6><p><i><?php echo __("Answer awaiting",'vamtam-product-qa');?>...</i></p></h6></td>
								</tr>
								<?php
							}
							$count++;
							if($count > $productQaLength){
								break;
							}
						} ?>
						</tbody>
						</table>
					</div>
					<?php endif; ?>
					<?php if ( count( $etsGetQuestion ) > $productQaLength ) : ?>
					<button type="submit" id="ets-load-more" class="btn btn-success" name="ets_load_more" value=""><?php echo $loadMoreButtonName; ?></button>
					<?php endif ?>
					<div class="ets_pro_qa_length"><p hidden><?php echo $keyData;?><p></div>
					<?php
				}
			} else {
				//Show Question Answer Listing Type Table With OUt Load More
				if(!empty($etsGetQuestion)){
					?>
					<div class="table-responsive my-table">
					<table class="table table-striped">
					<?php
					foreach ($etsGetQuestion as $key=>$value) {
						?>
						<tr class="ets-question-top">
								<td class="ets-question-title"><p><?php echo __('Question','vamtam-product-qa'); ?>:</p></td>
								<td class="ets-question-description"><p><?php echo $value['question'];?></p></td>
								<td class="ets-cont-right"><h6 class="user-name"><?php echo $value['user_name'] . "<br>";
								echo ($value['date']);
								?></h6></td>
						</tr>

						<?php
						if(!empty($value['answer'])){?>
							<tr>
								<td class="ets-question-title"><p><?php echo __('Answer','vamtam-product-qa'); ?>:</p></td>
								<td colspan="2"><p> <?php echo $value['answer'];?></p></td>
							</tr>
							<?php
						} else { ?>
							<tr>
								<td class="ets-question-title"><p><?php echo __('Answer','vamtam-product-qa'); ?>:</p></td>
								<td colspan="2" class="ets-no-answer"><h6><p><i><?php echo __("Answer awaiting",'vamtam-product-qa');?>...</i></p></h6></td>
							</tr>
							<?php
						}
					}
					?>
					</table>
					</div>
					<?php
				}
			}
			?>
		<div class="ets-question-detail-ajax" id="ets-question-detail-ajax"></div>

		<?php
	}

	/**
	* Load More Button Post Data Using Ajax
	*/
	public function load_more_qa(){
		if(!wp_verify_nonce($_GET['load_qa_nonce'],'ets-product-load-more-question')){
			echo json_encode(array('error' => "Access not allowed."));
			die;
		}
		$productId = intval($_GET['product_id']);
		$offsetdata = intval($_GET['offset']);
		$loadMoreButtonName = get_option('ets_load_more_button_name');
		$pagingType = get_option('ets_product_qa_paging_type' );
		$productQaLength = get_option('ets_product_q_qa_list_length');
		$etsGetQuestion = get_post_meta( $productId,'ets_question_answer', true );
		$offset = $offsetdata + $productQaLength;
		array_splice($etsGetQuestion,0,$offset);

		if(!empty($etsGetQuestion)){
			ob_start();
			$count = 1;

			//Show Question Answer Listing Accordion Type With Load More Button
			if($pagingType == 'accordion'){
				?>
				<div class='ets-qa-listing'>
				<?php
				foreach ($etsGetQuestion as $key => $value) {
					?>
					<div class="ets-accordion">
								<span class="que-content ans-content"><b><?php echo __('Question','vamtam-product-qa'); ?>:</b></span>
								<span class="que-content-des"><?php echo $value['question'];?></span>
								<h6><?php echo $value['user_name']. "<br>";?><?php echo $value['date'];?></h6>
							</div>
							<div class="ets-panel">
								<?php
								if(!empty($value['answer'])){?>
									<span class="ans-content"><b><?php echo __('Answer','vamtam-product-qa'); ?>:</b>
									</span>
									<span class="ans-content-des"><?php echo $value['answer'];?>
									</span>

							<?php
								} else { ?>
								<span class="ans-content"><b><?php echo __('Answer:','vamtam-product-qa'); ?></b></span>
								<span class="ans-content-des"><i><?php echo __("Answer awaiting",'vamtam-product-qa');?>...</i>
								</span>
								<?php
							}?>
							</div><?php
							$count++;
							if($count > $productQaLength){
								break;
							}
				}
				?>
				</div>
				<?php
			} else {
				//Show Question Answer Listing Type Table With Load More
				?>


				<?php

				 foreach ($etsGetQuestion as $key => $value) {
					?>
					<tr class="ets-question-top">
						<td class="ets-question-title"><p><?php echo __("Question","vamtam-product-qa");?>.</p></td>
						<td class="ets-question-description"><p><?php echo $value['question'];?></p></td>
						<td class="ets-cont-right"><h6 class="user-name"><?php echo $value['user_name'] . "<br>";
							echo ($value['date']);
							?></h6>
						</td>
					</tr>
					<?php
					if(!empty($value['answer'])){?>
						<tr>
							<td class="ets-question-title"><p><?php echo __("Answer","vamtam-product-qa");?>:</p></td>
							<td colspan="2"><p> <?php echo $value['answer'];?></p></td>
						</tr>
						<?php
					} else { ?>
						<tr>
							<td class="ets-question-title"><p><?php echo __("Answer:","vamtam-product-qa");?></p></td>
							<td colspan="2" class="ets-no-answer"><h6><p> <i><?php echo __("Answer awaiting",'vamtam-product-qa');?>...</i></p></h6></td>
						</tr>
						<?php
					}
					$count++;
					if($count > $productQaLength){
						break;
					}
				}
			}
			$htmlData = ob_get_clean();
		}
		$response = array(
			'htmlData'		=> $htmlData,
			'offset' 		=> $offset,
		);
		echo json_encode($response);
		die;
	}

	/**
	*  JS Variables
	*/
	public function qa_plugin_script() {
		wp_enqueue_script( 'vamtam_product_qa_script_js', VAMTAM_PRODUCT_QA_PATH . 'asset/js/vamtam_product_qa_script.js',array( 'jquery' ),'1.6',true  );
			$addQusNonce = wp_create_nonce('ets-product-add-new-question');
			$loadQaNonce = wp_create_nonce('ets-product-load-more-question');

			$script_params = array(
				'admin_ajax'		 => admin_url('admin-ajax.php'),
				'add_qustion_nonce'	 => $addQusNonce,
				'load_qa_nonce' 	 => $loadQaNonce

			);

		wp_localize_script( 'vamtam_product_qa_script_js', 'etsWooQaParams', $script_params );
	}

	public function qa_plugin_style() {
		wp_register_style(
			'vamtam_product_qa_style_css',
			VAMTAM_PRODUCT_QA_PATH. 'asset/css/vamtam_product_qa_style.css'
		);
		wp_enqueue_style( 'vamtam_product_qa_style_css');

	}
}

$vamtamWooProductUserQuestionAnswer = new USER_QUESTION_ANSWER();