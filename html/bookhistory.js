// Calls backend to pull all downloaded books stored in the database
function callGetBooks(query = "", type = "author") {
    let data = {};
    // Trim whitespace from search
    if (query.trim() !== "") {
        data[type] = query.trim();
    }
    // Clear prior data
    $("body .modal[id^='body']").remove();
    $.ajax({
            url: "/final.php/getBooks",
            method: "GET",
            dataType: "json",
            // Pass in search data
            data: data,
            success: function (data) {
                if (data.status === 0 && data.result) {
                    let rows = "";
                    let index = 0;
                    // Loop through each result and adds a table element for each result
                    data.result.forEach(function (book) {
                        rows += `
                        <tr>
                            <th scope="row">${book.bookid}</th>
                            <td>${book.promptid}</td>
                            <td>${book.timestamp || ""}</td>
                            <td>${book.author}</td>
                            <td>${book.title}</td>
                            <td>
                                <button type="button" class="bg-transparent border-0 text-primary" data-toggle="modal" data-target="#body${index}">view</button>
                            </td>
                        </tr>
                    `;
                    // Adds modal (integrated popup) to view the body of the book
                        $('body').append(
                            `
<div class="modal" id="body${index}" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content p-3">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">${book.title}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                ${book.body}
            </div>
        </div>
    </div>
</div>
                                `
                        );
                        index++;
                    });
                    // Add HTML to the table element 
                    $("#bookbody").html(rows);
                } else {
                    // Show error message if no books are present
                    console.log(data);
                    $("#bookbody").html('<tr><td colspan="6">No books found.</td></tr>');
                }
                
                
            },
            // Show error message backend can't be reached
            error: function (err) {
                $("#bookbody").html('<tr><td colspan="6">Error loading books.</td></tr>');
            }
        });
}

// Calls backed to retrieve API call data from the database
function callGetCost(query = "") {
    let data = {};
    // Trim whitespace from search
    if (query.trim() !== "") {
        data.prompt = query.trim();
    }
    $.ajax({
        url: "/final.php/getCost",
        method: "GET",
        dataType: "json",
        // Pass in search data
        data: data,
        success: function (data) {
            // If there's data to show
            if (data.status === 0 && data.result) {
                let rows = "";
                // Loop through data results and add them to HTML table
                data.result.forEach(function (cost) {
                    rows += `
                        <tr>
                            <td>${cost.promptid}</td>
                            <td>${cost.timestamp || ""}</td>
                            <td>${cost.tokens}</td>
                            <td>${cost.prompt}</td>
                        </tr>
                    `;
                });
                $("#costbody").html(rows);
            // If no data was found
            } else {
                $("#costbody").html('<tr><td colspan="4">No cost data found.</td></tr>');
            }
            // Calculate the USD cost of the tokens (show 0 if no data)
            $("#totalcost").text((data.totalcost || 0) + " ($" + (Math.round((data.totalcost * 0.0000025)*1000000)/1000000) + ")");
            $("#numbercalls").text(data.totalcalls || 0);

        },
        // Show error message if backend can't be reached
        error: function (err) {
            $("#costbody").html('<tr><td colspan="4">Error loading cost data.</td></tr>');
        }
    });
}

$(document).ready(function () {
    // Call functions on page startup (preloads tables)
    callGetBooks();
    callGetCost();
    
    // Call again on searches
    $("#getBooks").click(function () {
        const query = $("#bookPrompt").val().trim();
        const type = $("#bookSearchType").val();
        callGetBooks(query, type);
    });

    $("#getCost").click(function () {
        const query = $("#costPrompt").val().trim();
        callGetCost(query);
    });
});