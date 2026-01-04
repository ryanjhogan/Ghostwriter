eBook Builder
CSE 383 - Miami University
This is a small PHP + JavaScript project that generates short eBooks (EPUB) from user prompts using the OpenAI API.

## Features
- Build EPub formatted files containing a pregenerated and editable story
- Add a cover photo and author details
- View prior book downloads, as well as the contents of the books
- Track API token usage and calculate expected costs

## Quick start (development)
1. Copy the repository and open the project folder.
2. Create a `.env` file (same directory as `docker-compose.yaml`) with:

   APIKEY=your_openai_api_key_here
   PASSWORD=chosen_password

3. Start the app:

```bash
docker compose up -d
```

4. Open `http://localhost/makebook.html` to start creating your own book.
    or `http://localhost/index.html` for more details

Notes:
- The container expects the environment variables `APIKEY` and `PASSWORD` to be available. You can provide it by creating a `.env` file or `env_file` in the same directory as `docker-compose.yaml`
- PHPLiteAdmin is accessible at `http://localhost/phpliteadmin`, use set password (in .env) to login

## Troubleshooting
- If OpenAI calls hang, the backend has cURL timeouts; ensure `APIKEY` is set and network egress is allowed.
- The SQLite DB is located at `html/cse383.db`. By default it is empty, but usage will eventually fill it with values that will be displayed in the prior books page.

## Future improvements
- Use unique temp directories when building EPUBs to avoid concurrent request collisions.
- Harden input validation for uploads and SQL parameters.
- Allow users to select which model they would like to use.

## Utilizes
- SortTable `https://sortablejs.github.io/Sortable/`
- PHPLiteAdmin `https://www.phpliteadmin.org/`
- LibZip `https://libzip.org/`