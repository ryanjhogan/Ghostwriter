document.addEventListener('DOMContentLoaded', function () {
    const apiTabBtn = document.getElementById('apitablink');
    const bookTabBtn = document.getElementById('booktablink');
    const apiSection = document.getElementById('api');
    const booksSection = document.getElementById('books');

    apiTabBtn.addEventListener('click', function (e) {
        e.preventDefault();
        apiTabBtn.classList.add('active');
        bookTabBtn.classList.remove('active');
        apiTabBtn.classList.remove('bg-transparent');
        bookTabBtn.classList.add('bg-transparent');
        apiSection.classList.remove('d-none');
        booksSection.classList.add('d-none');
    });

    bookTabBtn.addEventListener('click', function (e) {
        e.preventDefault();
        bookTabBtn.classList.add('active');
        apiTabBtn.classList.remove('active');
        apiTabBtn.classList.add('bg-transparent');
        bookTabBtn.classList.remove('bg-transparent');
        apiSection.classList.add('d-none');
        booksSection.classList.remove('d-none');
    });
});
