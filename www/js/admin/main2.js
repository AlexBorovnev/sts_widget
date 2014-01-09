function _widgetType(obj){
	var $container = $(".widgetTypeList");
	
	//remove
		
	$container.append("<option value=\""+obj.id+"\">"+obj.title+"</option>")	
	
	$container.bind('change', function(){
		var title = $(this).find('option:selected').html()
		$(".widgetType").html();
	})
}

function _widgetSkin(obj){
	var $container = $(".widgetSkinList");
	$container.append("<option value=\""+obj.id+"\">"+obj.title+"</option>")  
}
function buildColorList(colorList){
	var $ul =  $("<ul></ul>");
    _colorList = colorList || _colorList;
	for(var i in _colorList){
		var colorName = _colorList[i];
		var color = "#";
		switch(colorName){
			case 'Бежевый':color += "F5F5DC";
			break;
			case 'Белый':color += "FFF";
			break;
			case 'Голубой':color += "00BFFF";	
			break;
			case 'Желтый':color += "FFFF00";
			break;
			case 'Зеленый':color += "00FF00";
			break;
			case 'Золотой':color += "FFD700";
			break;
			case 'Коричневый':color += "964B00";
			break;
			case 'Красный':color += "FF0000";
			break;
			case 'Мультицвет':color += "MULTICOLOR";
			break;
			case 'Не указан':color += "";
			break;
			case 'Оранжевый':color += "FF4F00";
			break;
			case 'Розовый':color += "FFC0CB";
			break;
			case 'Серебряный':color += "C0C0C0";
			break;
			case 'Серый':color += "808080";
			break;
			case 'Синий':color += "3A75C4";
			break;
			case 'Фиолетовый':color += "8B00FF";
			break;
			case 'Черный':color += "000";
			break;
		}
		
//		if(color == 'MULTICOLOR')
	 	$ul.append('<li class="color dev-editor-color" data-color-name="'+colorName+'" data-color="'+color+'">'+colorName+'</li>')
	 	
	}
	
	return $ul;
}



function getCategoryList(shopId, cb){
	api.call('getCategoryList', {shopId: shopId}, cb);
		//$(".treeTpl").append($tree);

        //$tree.find(".Content").bind('click', function(){

//				var cid = $(this).parent().data('cid'),
//				pid = $(this).parent().data('pid');

//				$tree.find(".previewPic img").attr('src', './img/preview.png');
//				$tree.find(".offerInfo").empty();
//                
//				if(pid != 0){
//					$tree.find(".Content").removeClass('b');
//					$(this).addClass('b');
//					$tree.find(".noOffers").remove();
//					$tree.find(".offerHolder li").remove();
//					getOfferList(cid, shopId, widget);
//				}
//				else{

//					$(this).prev().trigger('click');
//					var event = {};
//					event.target = $(this).prev()[0];
//					tree_toggle(event);
//				}
//			});
}






function _position(){
	
}



function buildCategoryList(categories){
	if(categories.length == 0){
		toastr.error('нет категорий для отображения');
		return;
	}

	var cats = {};
	for(var i in categories.list){
		var c = categories.list[i];

		var cid = c.category_id;
		var pid = c.parent_id;
		var t = {
			cid: cid,
			pid: pid,
			title: c.title

		};
		if(pid == 0){

			cats[cid] = t;
		}
		else{
			if(cats[pid]['childs'] == undefined)
				cats[pid]['childs'] = {};
			cats[pid]['childs'][cid] = t;
		}

	}
	return cats;
}
