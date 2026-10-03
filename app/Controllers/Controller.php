<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;

/**
 * Base controller — shared conveniences for input validation,
 * validation-error flashing and view rendering.
 */
abstract class Controller
{
    /**
     * Validate request input; on failure flashes errors + old input
     * and redirects back (or returns the errors array for inline use).
     *
     * @param array<string, string> $rules
     * @return array{ok: bool, data: array<string, mixed>, errors: array<string, string>}
     */
    protected function validate(Request $request, array $rules, bool $redirectBack = true): array
    {
        $validator = Validator::make($request->all(), $rules);

        if ($validator->passes()) {
            return ['ok' => true, 'data' => $validator->validated(), 'errors' => []];
        }

        $errors = $validator->firstErrors();

        if ($redirectBack) {
            Session::put('_errors', $errors);
            Session::flashInput($request->all());
            echo Response::back();
            exit;
        }

        return ['ok' => false, 'data' => [], 'errors' => $errors];
    }

    protected function json(array $data, int $status = 200): string
    {
        return Response::json($data, $status);
    }
}
