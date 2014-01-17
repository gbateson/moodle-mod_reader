$(document).ready(function(){
    $("#id_courseid").change( function(){ 
        $("#loadersection").toggle();
        $.post("ajax_loadsectionoption.php?id=" + $(this).val(), function(data){
            $("#id_section").html(data);
            $("#loadersection").toggle();
        });
    });
    
    $("#id_section").click( function(){ 
        $("input[name=sectionchoosing]").attr("checked", true);
    });
});