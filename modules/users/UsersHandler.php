<?php

class UsersHandler implements HandlerInterface
{
    private UserQuery $userQuery;

    public function __construct(UserQuery $userQuery)
    {
        $this->userQuery = $userQuery;
    }

    public function handle(array $request): Response
    {
        $users = $this->userQuery->getAllUsers();
        return view("users/views/index", ['users' => $users], "default");
    }
}
