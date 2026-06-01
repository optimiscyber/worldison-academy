<?php
/**
 * UserRepository — User DB queries.
 */
require_once __DIR__ . '/BaseRepository.php';

class UserRepository extends BaseRepository {

    /**
     * Get user by ID.
     */
    public function getUserById($user_id) {
        return $this->fetchOne(
            "SELECT id, name, email, role, profile_picture, bio FROM users WHERE id = ? LIMIT 1",
            [$user_id]
        );
    }

    /**
     * Get user by email.
     */
    public function getUserByEmail($email) {
        return $this->fetchOne(
            "SELECT * FROM users WHERE email = ? LIMIT 1",
            [$email]
        );
    }

    /**
     * Create user.
     */
    public function createUser($name, $email, $password_hash, $role = 'student') {
        $this->execute(
            "INSERT INTO users (name, email, password, role, created_at)
             VALUES (?, ?, ?, ?, NOW())",
            [$name, $email, $password_hash, $role]
        );
        return $this->lastInsertId();
    }

    /**
     * Update profile picture.
     */
    public function updateProfilePicture($user_id, $picture_path) {
        $this->execute(
            "UPDATE users SET profile_picture = ? WHERE id = ?",
            [$picture_path, $user_id]
        );
        return true;
    }

    /**
     * Get instructor by ID (minimal info for display).
     */
    public function getInstructor($user_id) {
        return $this->fetchOne(
            "SELECT id, name, profile_picture, bio FROM users WHERE id = ? AND role = 'instructor' LIMIT 1",
            [$user_id]
        );
    }
}
