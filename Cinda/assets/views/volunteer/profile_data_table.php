<table class="widefat fixed tabla">
	<thead>
		<tr>
			<th style="width:30%">Field</td>
			<th>Value</td>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td>Nickname</td>
			<td><input type="text" name="<?php echo CINDA_PREFIX; ?>nickname" value="<?php echo $fields[CINDA_PREFIX.'nickname'][0]; ?>"></td>
		</tr>
		<tr>
			<td>Email</td>
			<td><input type="email" name="<?php echo CINDA_PREFIX; ?>email" value="<?php echo $fields[CINDA_PREFIX.'email'][0]; ?>"></td>
		</tr>
		<tr>
			<td>Device ID</td>
			<td><input type="text" name="<?php echo CINDA_PREFIX; ?>device_id" readonly value="<?php echo $fields[CINDA_PREFIX.'device_id'][0]; ?>"></td>
		</tr>
		<tr>
			<td>Avatar</td>
			<td><img src="<?php echo $fields[CINDA_PREFIX.'avatar_url'][0]; ?>"  style="border-radius:50%;"/></td>
		</tr>
	</tbody>
</table>