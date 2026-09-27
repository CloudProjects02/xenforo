var DBTech = window.DBTech || {};
DBTech.Shop = window.DBTech.Shop || {};

!((window, document) =>
{
	// ################################## --- ###########################################
	DBTech.Shop.InfiniteScroll = XF.Element.newHandler({
		init ()
		{
			let $grid = this.target;

			let scroller = new InfiniteScroll(this.target, {
				button: '.item-button',
				append: '.itemList-item',
				hideNav: '.block-outer--pagination',
				path: '.block-outer--pagination .pageNav-jump--next',
				status: '.item-status',
				history: $grid.getAttribute('data-infinite-scroll-history') ? 'push' : false
			});

			let scrollStatus = $grid.querySelector('.item-status');
			let scrollLoader = $grid.querySelector('.item-loader');

			scroller.on('last.infiniteScroll', () =>
			{
				scrollStatus.style.display = 'none';
				scrollLoader.style.display = 'none';
			});

			if ($grid.getAttribute('data-infinite-scroll-click'))
			{
				if ($grid.getAttribute('data-infinite-scroll-after'))
				{
					scroller.on('load.infiniteScroll', () =>
					{
						if (scroller.getAttribute('data-infiniteScroll').loadCount == $grid.getAttribute('data-infinite-scroll-after'))
						{
							scrollLoader.style.display = 'block';

							scroller.infiniteScroll('option', { loadOnScroll: false });
							scroller.off('load.infiniteScroll', onPageLoad);
						}
					});
				}
				else if (scrollLoader)
				{
					scrollLoader.style.display = 'block';
					scroller.infiniteScroll('option', { loadOnScroll: false });
				}
			}
		}
	});

	// ################################## --- ###########################################

	XF.Element.register('dbtech-shop-infinite-scroll', 'DBTech.Shop.InfiniteScroll');
})(window, document)