$(document).ready(function(){
	initializeSearch("#ghost_searchinput", "#ghost_searchresult");
});



function initializeSearch(inputSelector, resultSelector){
	var test = $(inputSelector);

	$(inputSelector).change(function() {
		onInputChanged($(this).val());
	});


	function onInputChanged(searchTerms){

		clearResult();
		search(searchTerms);			
	}


	function renderSearchResult(result){
		$.each(result, function (index, item) {
			renderItem(item);
		});
	}


	// Renders an item and appends it to the element selected using the "resultSelector". 
	function renderItem(item){
		var documentFolder = getDocumentFolder();

		var articleElement = document.createElement("article");
		articleElement.setAttribute("class", "post");
		var headerElement = document.createElement("header");
		headerElement.setAttribute("class", "post-header");	

		
		var titleElement = document.createElement("H2");
		titleElement.setAttribute("class", "post-title");
		

		var titleAnchorElement = document.createElement("a");
		titleAnchorElement.setAttribute("href", documentFolder + item.slug);	
		$(titleAnchorElement).text(item.title);

		$(titleElement).append(titleAnchorElement);	

		$(headerElement).append(titleElement);
		$(articleElement).append(headerElement);

		var sectionElement = document.createElement("section");
		sectionElement.setAttribute("class", "post-excerpt");
		
		var paragraphElement = document.createElement("p");
		$(paragraphElement).text(item.search_preview);

		var previewAnchorElement = document.createElement("a");
		previewAnchorElement.setAttribute("class", "read-more");
		previewAnchorElement.setAttribute("href", documentFolder + item.slug);
		$(previewAnchorElement).text("\u00BB");	

		$(paragraphElement).append(previewAnchorElement);	

		$(sectionElement).append(paragraphElement);

		$(articleElement).append(sectionElement);

		$(resultSelector).append(articleElement);
	}


	function clearResult(){
		$(resultSelector).empty();
	}


	// Performs the actual search and returns the result as JSON.
	function search(searchTerms){		
		$.get( "ghostsearch.php?s=" + searchTerms, function( data ) {
			renderSearchResult(data);		
		}, "json");
	};

	function getDocumentFolder(){
		var path = document.location.pathname;
		var dir = path.substring(path.indexOf('/', 0), path.lastIndexOf('/') + 1);
		return dir;
	}


};