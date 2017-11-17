<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$app->get('/', function () use ($app) {
    return $app->json([
        'Available methods for book' => [
            'GET' => 'All list or one by ID',
            'PUT' => 'Create new book',
            'POST and PATCH' => 'Edit book by ID',
            'DELETE' => 'Delete book by ID',
        ],
    ]);
})
->bind('homepage')
;

$app->get('book/{id}', function ($id) use ($app) {
    $sql = 'SELECT * FROM book';
    if ($id) {
        $sql .= ' WHERE id = ?';
    }

    $result = $app['db']->fetchAll($sql, [$id]);

    return $app->json($result);
})
->convert('id', function ($id) { return (int) $id; }) # кастуем к числу
->value('id', 0) # для вывода всего списка (значение по умолч.)
;

$app->put('book', function (Request $request) use ($app) {
    if (!$request->get('name')) {
        $app->abort(403, 'Field name is required');
    }

    $sql = 'INSERT INTO book (`name`, authors, `year`) VALUES (?, ?, ?)';

    $result = $app['db']->executeUpdate($sql, [
        $request->get('name'),
        $request->get('authors'),
        (int) $request->get('year'),
    ]);

    if (!$result) {
        $app->error(function () { return new Response('Ошибка БД'); });
    }

    return $app->json(['id' => $app['db']->lastInsertId('id')]);
})
;

$app->match('book/{id}', function (Request $request, $id) use ($app) {
    $sql = 'SELECT * FROM book WHERE id = ?';
    $book = $app['db']->fetchAssoc($sql, [$id]);
    if (empty($book)) {
        $app->abort(404, "Book {$id} does not exist");
    }

    $sqlUpdate = 'UPDATE book SET name = ?, authors = ?, year = ? WHERE id = ?';
    $result = $app['db']->executeUpdate($sqlUpdate, [
        $request->get('name', $book['name']),
        $request->get('authors', $book['authors']),
        $request->get('year', $book['year']),
        $id,
        ]
    );
    if (!$result) {
        $app->error(function () { return new Response('Ошибка обновления БД'); });
    }

    return $app->json($app['db']->fetchAssoc($sql, [$id]));
})
->method('POST|PATCH') # разрешаем оба типа
->assert('id', '\d+') # принимаем только цифры
->convert('id', function ($id) { return (int) $id; }) # кастуем к числу
;

$app->delete('book/{id}', function ($id) use ($app) {
    $sql = 'DELETE FROM book WHERE id = ?';

    $result = $app['db']->executeUpdate($sql, [$id]);

    if (!$result) {
        $app->error(function () { return new Response('Ошибка БД'); });
    }

    return $app->json(['id' => $id]);
})
->assert('id', '\d+') # принимаем только цифры
->convert('id', function ($id) { return (int) $id; }) # кастуем к числу
;

$app->error(function ($code) use ($app) {
    if ($app['debug']) {
        return;
    }

    return new Response('error', $code);
});
