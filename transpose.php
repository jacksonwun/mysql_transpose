function execute_multiline_sql($sql) {
    global $wpdb;
    $sqlParts = array_filter(explode("\r\n", $sql));
    foreach($sqlParts as $part) {
        $wpdb->query($part);
        if($wpdb->last_error != '') {
            $error = new WP_Error("dberror", __("Database query error"), $wpdb->last_error);
            $wpdb->query("rollback;");
            return $error;
        }
    }
    return true;
}

function get_data(){
	global $wpdb;
	$wp_posts                        = $wpdb->prefix . 'posts';
	$wp_postmeta                     = $wpdb->prefix . 'postmeta';
	$wp_order_product_lookup         = $wpdb->prefix . 'wc_order_product_lookup';
	$wp_wc_order_stats               = $wpdb->prefix . 'wc_order_stats ';
	$wp_wc_order_product_lookup      = $wpdb->prefix . 'wc_order_product_lookup ';
	
	if ( strcmp($_POST['status'] ,"all") == 0 ){
		$selected_status = "''wc-pending'',''wc-processing'',''wc-on-hold'',''wc-completed'',''wc-cancelled'',''wc-refunded'',''wc-failed''";
	}else{
		$selected_status = "''".$_POST['status']."''";
	}
	
	$sql = "SET @query = NULL;";
	$wpdb->query($sql);
	$sql = "
		/* Select the product qty with product title */
		SELECT GROUP_CONCAT(DISTINCT CONCAT( 
			'MAX(CASE WHEN product_id = ''', 
			product_id, 
			''' THEN product_qty else 0 end) as ''', 
			INSERT(product_name_list.product_name,1,3,''), 
			'''')) INTO @query 
		FROM ".$wp_order_product_lookup." AS opl
		INNER JOIN(
			SELECT 
				p.ID         as id,
				if(opl.variation_id = 0 ,post_title, (SELECT ".$wp_posts.".post_title FROM ".$wp_posts." WHERE ".$wp_posts.".id  = opl.variation_id)) as product_name
			FROM 
				".$wp_posts." AS p
			INNER JOIN
				".$wp_order_product_lookup." AS opl
				ON p.id = opl.product_id
			WHERE 
				p.post_type = 'product_variation' OR p.post_type = 'product'
			) AS product_name_list
			ON product_name_list.id = opl.product_id;
	";
	$wpdb->query($sql);
	$sql = "
		SET @query = CONCAT('
				SELECT ops.order_id, ops.status, main_table.*,', 
				@query,'
				FROM (
					SELECT 
						pm.post_id                                                                            		       AS order_id,
						CONCAT( GROUP_CONCAT( IF(pm.meta_key=''_billing_first_name'', pm.meta_value, NULL) ), '' '', 
		   					GROUP_CONCAT( IF(pm.meta_key=''_billing_last_name'', pm.meta_value, NULL) ))  	         AS Name,
							GROUP_CONCAT( IF(pm.meta_key=''_instagram_id'', pm.meta_value, NULL) )      	         AS IG,
							GROUP_CONCAT( IF(pm.meta_key=''_time_slot'', pm.meta_value, NULL) )  	         AS Time_Slot,
							GROUP_CONCAT( IF(pm.meta_key=''_payment_method'', pm.meta_value, NULL) )      	       AS Payment_Method,
							GROUP_CONCAT( IF(pm.meta_key=''_order_total'', pm.meta_value, NULL) )                 	         AS Total,
							GROUP_CONCAT( IF(pm.meta_key=''_paid_date'', pm.meta_value, NULL) )                              AS Paid_Date

					FROM 
						".$wp_postmeta." AS pm 

					WHERE 
						pm.post_id IN ( SELECT DISTINCT ".$wp_wc_order_stats.".order_id FROM ".$wp_wc_order_stats." )

						GROUP BY pm.post_id

					) AS main_table /* Choose data from post when it has order stats */
				INNER JOIN
					".$wp_order_product_lookup." as opl
					ON opl.order_id = main_table.order_id
				INNER JOIN
					".$wp_wc_order_stats." AS ops 
					ON ops.order_id = opl.order_id
				WHERE
					ops.status IN (".$selected_status.")  
					GROUP BY ops.order_id
			 ;');
	";
	$wpdb->query($sql);
	$sql = "PREPARE test FROM @query;";
	$sql_pre = $wpdb->query($sql);
	$sql_pre = "EXECUTE test;";
	$results = $wpdb->get_results($sql_pre);

	echo json_encode( $results );
	die();
}
add_action( 'wp_ajax_get_all_order', 'get_data' );
add_action( 'wp_ajax_nopriv_get_all_order', 'get_data' );
