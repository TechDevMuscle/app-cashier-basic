<?php

// Halaman awal wajib login; pengguna guest diarahkan ke form.
test('guest is redirected to login', function () {
    $response = $this->get('/');

    $response->assertRedirect('/login');
});
