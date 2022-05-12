/* Select the product qty with product title */
SELECT GROUP_CONCAT(DISTINCT CONCAT( 
	'MAX(CASE WHEN product_id = ''', 
	product_id, 
	''' THEN product_qty else 0 end) as ''', 
	INSERT(product_name_list.product_name,1,3,''), 
	'''')) INTO @query 
FROM ".$wp_order_product_lookup." AS opl
INNER JOIN(
	/* Select Order ID and Product Name from wp_posts and wp_order_product_lookup  */
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
  
  /* Concat data to a line of script */
SET @query = CONCAT('
		/* Select id, status and remaining data from wp_postmeta(filtered), wp_order_product_lookup and wp_wc_order_stats */
		SELECT ops.order_id, ops.status, main_table.* ,', 
		@query,'
		FROM (
			/* Choose data from post when it has order stats */
			SELECT 
				pm.post_id                                                                            		       AS order_id,
				CONCAT( GROUP_CONCAT( IF(pm.meta_key=''_billing_first_name'', pm.meta_value, NULL) ), '' '', 
   					GROUP_CONCAT( IF(pm.meta_key=''_billing_last_name'', pm.meta_value, NULL) ))  	               AS Name,
					GROUP_CONCAT( IF(pm.meta_key=''_instagram_id'', pm.meta_value, NULL) )      	               AS IG,
					GROUP_CONCAT( IF(pm.meta_key=''_time_slot'', pm.meta_value, NULL) )  	                       AS Time_Slot,
					GROUP_CONCAT( IF(pm.meta_key=''_payment_method'', pm.meta_value, NULL) )      	               AS Payment_Method,
					GROUP_CONCAT( IF(pm.meta_key=''_order_total'', pm.meta_value, NULL) )             	       AS Total,
					GROUP_CONCAT( IF(pm.meta_key=''_paid_date'', pm.meta_value, NULL) )                            AS Paid_Date
			FROM 
				".$wp_postmeta." AS pm 
			WHERE 
				pm.post_id IN ( SELECT DISTINCT ".$wp_wc_order_stats.".order_id FROM ".$wp_wc_order_stats." )
				GROUP BY pm.post_id
			) AS main_table
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