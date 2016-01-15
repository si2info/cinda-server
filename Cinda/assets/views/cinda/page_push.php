<h1><?php _e('Notifications','Cinda')?></h1>
<div class="page-content">
	<form method="post">

	
		<table class="form-table parse">
			<tbody>
				
					<input type="hidden" name="<?php echo CINDA_PREFIX; ?>push_action" value="parse">
					<tr>
						<th colspan="2"><h3><strong><?php _e('Parse','Cinda')?></strong></h3></th>
					</tr>
					<tr>
						<th><?php _e('Title','Cinda')?></th>
						<td>
							<input type="text" name="<?php echo CINDA_PREFIX; ?>push_title" size="30" placeholder="<?php _e('Write your custom title','Cinda'); ?>" id="title" spellcheck="true" autocomplete="off">
						</td>
					</tr>
					
					<tr>
						<th><?php _e('Text / Message','Cinda')?></th>
						<td>
							<?php 
								/* <textarea name="<?php echo CINDA_PREFIX; ?>push_text" placeholder="<?php  _e('Type your notification text here.','Cinda'); ?>"></textarea> */
								wp_editor( '', "pushmessage", array( 'media_buttons' => false,'quicktags' => false ) );
							
							?>
						</td>
					</tr>
					
					<tr>
						<th><?php _e('Send to','Cinda')?></th>
						<td>
							<input type="checkbox" name="<?php echo CINDA_PREFIX; ?>push_send_to" class="switchButton All" >
						</td>
					</tr>
					
					<tr class="hidden" data-for="<?php echo CINDA_PREFIX; ?>push_send_to">
						<th><?php _e('Campaign','Cinda')?></th>
						<td>
							<select name="<?php echo CINDA_PREFIX; ?>push_send_to_cid" class="select2" disabled="true" required="true">
								<option value=""><?php _e("Select campaign")?></option>
								<?php 
									if(0 < count($campaigns)){
										foreach($campaigns AS $campaign){
											echo "<option value=\"".$campaign['id']."\">".$campaign['title']."</option>";
										}
									
									}
								?>
							</select>
						</td>
					</tr>
					
					<tr>
						<th><?php _e('¿Related campaign?','Cinda')?></th>
						<td>
							<input type="checkbox" name="<?php echo CINDA_PREFIX; ?>push_active_campaign" class="switchButton On" >
						</td>
					</tr>

					<tr class="hidden" data-for="<?php echo CINDA_PREFIX; ?>push_active_campaign">
						<th><?php _e('Campaign','Cinda')?></th>
						<td>
							<select name="<?php echo CINDA_PREFIX; ?>push_cid" class="select2" disabled="true" required="true">
								<option value=""><?php _e("Select campaign")?></option>
								<?php 
									if(0 < count($campaigns)){
										foreach($campaigns AS $campaign){
											echo "<option value=\"".$campaign['id']."\">".$campaign['title']."</option>";
										}
									}
								?>
							</select>
						</td>
					</tr>
					
					<tr>
						<th></th>
						<td><button class="button-export export_campaigns"><i class="fa fa-paper-plane"></i>  <?php _e('Send notification','Cinda'); ?></button></td>
					</tr>
			</tbody>
		</table>
	</form>
</div>