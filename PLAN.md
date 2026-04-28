Make me a small laravel reverb server. I will functioning as a docker image that only purpose is to be a lightweight websocket server, using laravel reverb.

So the idea is to spin up a server and then set the broadcast connection and PUSHER_APP_ID etc in another laravel project to use this.

I also want a dashboard for logged in user , default seed user 1 admin@admin.com password: testing123

Here we can see status of the websocket, and test that its working by sending and receving ping. Maybe some config options.

I also want a statistics of how many websockets have been sent , how much usage this have. just set up a small sqlite database for this.

Create docker images and docker compose for me to easily get it up. Create Makefile for easy handling and several useful commands.

When we do "make up", it will docker compose create everything and set it up, then echo "Site is ready at http://reverb-test.test:8120 and list .env values there we could copy right into an exisiting laravel system"

