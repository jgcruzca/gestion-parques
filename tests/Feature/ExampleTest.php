<?php

it('redirects the home page', function () {
    $this->get('/')->assertRedirect();
});
