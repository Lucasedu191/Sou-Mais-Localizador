(function () {
	const notices = document.querySelectorAll('.notice.is-dismissible');
	notices.forEach((notice) => {
		const button = notice.querySelector('.notice-dismiss');
		if (button) {
			button.addEventListener('click', () => {
				notice.classList.add('sm-dismissed');
			});
		}
	});
})();
