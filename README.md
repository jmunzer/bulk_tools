# PARA_UPD

## Running with Docker

- First build the image.

    ```bash
    docker build -t para_upd .
    ```

- Run up a container, this will mount the src folder locally.

    ```bash
    docker run -p 127.0.0.1:8081:80/tcp --name para_upd -v $PWD/src:/var/www/html para_upd
    ```

- Access container that is running for debugging.

    ```bash
    docker exec -it para_upd bash
    ```
