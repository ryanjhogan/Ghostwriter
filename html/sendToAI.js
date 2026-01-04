const buildBookUrl = "/final.php/buildBook";
const test = "/final.php/build"
const logCostUrl = "/final.php/logCost";
const callAIUrl = "/final.php/callAI";


$(document).ready(function() {
    // On send
    $("#send").click(function() {    
        //Displays loading div 
        $("#loading").toggleClass('hidec');
        $("#send").prop("disabled", true);  

        var topic = $("#idea").val();

        $.ajax ({
            url: callAIUrl,
            method: "POST",
            data: {
                topic: topic
            }
        }).done(function(parsedResponse) {
            $("#response").toggleClass('d-none');
            $("#loading").toggleClass('hidec');

            var response = parsedResponse.response;
            var tokens = parsedResponse.tokens;
        
            //Edits out weird HTML formatting chatGPT adds
            response = response.replace("```html", "").replace("```", "").replaceAll("<p></p>", "").trim();

            //Adds the reponse to a editable div
            $("#entry").append(response);

            //Add save button, author and cover prompt 
            $("#save").toggleClass('d-none');
            $("#author-div").toggleClass('hidec');
            $("#covers").toggleClass('d-none');
            //Add cost of API call to "cost" SQL database
            $.ajax({
                url: logCostUrl, // Replace with your server endpoint
                method: "POST",
                data: {
                    tokens: tokens,
                    prompt: topic
                },
                success: function (response) {
                    $("#promptId").val(response.promptid);
                },
                error: function (error) {
                    console.error("Log cost:" + error.responseText);
                }
            });
        }).fail(function(error) {
            //Hides loading div
            $("#loading").toggleClass('hidec');
            //Displays a fail window otherwise
            alert("Error: Couldn't connect to OpenAI Servers");
        });
    });

    // On save
    $("#save").click(function () {
    var $copy = $("#entry").clone();
    $copy.find("h1").remove();
    // Grab all necessary data
    var body = $copy.html();
    var authorName = $("#author").val().trim();
    var title = $("#response h1").text();
    var chapters = $("#response h3").map(function() {
        return $(this).text();
    }).get();
    var coverPhoto = $("#coverPhoto")[0].files[0];

    // Makes sure all details are filled
    if (!authorName || !coverPhoto || !body) {
        alert("Please fill all required fields.");
        return;
    }

    // Fill the hidden form
    $("#formTitle").val(title);
    $("#formChapters").val(chapters);
    $("#formAuthor").val(authorName);
    $("#formBody").val(body);

    // Set the file input
    var fileInput = document.getElementById('formCoverPhoto');
    var dataTransfer = new DataTransfer();
    dataTransfer.items.add(coverPhoto);
    fileInput.files = dataTransfer.files;

    // Submit the form
    $("#downloadForm").submit();
    });
    
});