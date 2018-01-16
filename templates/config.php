<form action="?" method="post">
	<?php $this->model->_CSRF->csrfInput(); ?>
    <input type="hidden" name="empty" value="1" />

	<p>
		<input type="submit" value="Empty forms cache" />
	</p>
</form>