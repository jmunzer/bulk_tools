# PARA_UPD

## Running with Docker

We assume that you have a working docker engine on your machine. You can download an easy to use docker desktop application from <https://docs.docker.com/get-docker/>

- First build the image.

    ```bash
    # in the project root directory.
    docker build -t para_upd .
    ```

- Run up a container, this will mount the src folder locally for easy dev work.

    ```bash
    docker run -p 127.0.0.1:8081:80/tcp --name para_upd -v $PWD/src:/var/www/html para_upd
    ```

    You'll also now be able to access the app on <http://localhost:8081> Change the local port number in the above command if you need a different port to avoid a clash.

- Access container that is running for debugging.

    ```bash
    docker exec -it para_upd bash
    ```

- Stop a running container

    ```bash
    # list all running docker processes.
    docker ps
    docker stop para_upd
    ```

- If you have to rebuild the image, you might need to delete the container to use the same name again.

    ```bash
    docker rm para_upd
    ```
