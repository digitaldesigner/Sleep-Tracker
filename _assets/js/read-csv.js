function doAFadeIn(msg){
	$('#inputOuter').fadeOut(400,function(){
    $('#output').html(msg).promise().done(function(){
      $('#output .summary').fadeIn(400,function(){
        $("#output section").each(function(i){
          $(this).delay(i * 300).fadeIn(500);
        });          
      });
    });
	});
}

options = {
    success: function(files) {
      var chosenfile = files[0].link;
    	var days = $('#days').val();
    	$.ajax({
    		type: "POST",
    		data: {url:chosenfile,days:days},
    		url: "remote.php",
        beforeSend: function(){
          $('#dropzone').addClass('dropbox').show();
        },success: function(msg){
    			doAFadeIn(msg);
    		},complete: function(){
    		  $('#dropzone').removeClass().hide();
    		}
    	});
    },
    linkType: "direct",
    multiselect: false,
    extensions: ['.csv'],
    folderselect: false
};

var button = Dropbox.createChooseButton(options);
document.getElementById("dropbox").appendChild(button);


function handleFiles(files) {
	if (window.FileReader) {
		if(files[0]['type'] == "text/csv") {
			getAsText(files[0]);
		} else {
			$('#inputInner').append('<p class="error">Only .csv files are supported.</p>');
		}
	} else {
		$('#inputInner').append('<p class="error">This browser isn\'t supported.</p>');
	}
}

var $form = $('body');
var droppedFiles = false;
$form.on('drag dragstart dragend dragover dragenter dragleave drop', function(e){
	e.preventDefault();
	e.stopPropagation();
}).on('dragover dragenter', function() {
	$('#dropzone').removeClass().show();
}).on('drop', function(e) {
	$('#dropzone').addClass('done').delay(600).fadeOut(350,function(){
		$('#dropzone').removeClass().hide();
	});
	droppedFiles = e.originalEvent.dataTransfer.files;
	handleFiles(droppedFiles);
});

function getAsText(fileToRead) {
	var reader = new FileReader();
	reader.onload = loadHandler;
	reader.onerror = errorHandler;
	reader.readAsText(fileToRead);
}

function errorHandler(evt) {
	if(evt.target.error.name == "NotReadableError") {
		$('#input').append('<p class="error">Can\'t read the file.</p>');
	}
}

function loadHandler(event,file) {
	var days = $('#days').val();
	if (event){ var csv = event.target.result; }
	else { var csv = false; }
	$.ajax({
		type: "POST",
		data: {data:csv,file:file,days:days},
		url: "read.php",
		success: function(msg){
      doAFadeIn(msg);
		}
	});
}

$('#existing').click(function(e){
	e.preventDefault();
	loadHandler(false,$(this).attr('href'));
});

$("body").on("click","a.explode", function(e) {
  e.preventDefault();
  e.stopPropagation(); 
  $(this).toggleClass('big');
});