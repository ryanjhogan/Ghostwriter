document.getElementById('coverPhoto').addEventListener('change', function (event) {
    // Get the selected file
    const file = event.target.files[0]; 
    // Select the <img> element
    const imgElement = document.querySelector('#covers img'); 

    if (file) {
        // Create a FileReader to read the file
        const reader = new FileReader();

        reader.onload = function (e) {
            // Set the <img> src to the file's data URL
            imgElement.src = e.target.result; 
            // Set the alt attribute to the file name
            imgElement.alt = file.name;
        };
        // Read the file as a data URL
        reader.readAsDataURL(file); 
    }
});