<?php

class UserQuery
{
    private Db $db;

    public function __construct(Db $db)
    {
        $this->db = $db;
    }

    public function getAllUsers(): array
    {
        return $this->db->findAll("SELECT id, name, email FROM users ORDER BY id ASC");
    }
}
