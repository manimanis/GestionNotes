<?php
/**
 * Validator - Validation des données d'entrée
 */

namespace App\Helpers;

class Validator
{
    private array $errors = [];
    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Valider qu'un champ est requis
     */
    public function required(string $field, string $label = ''): self
    {
        $label = $label ?: $field;
        if (!isset($this->data[$field]) || trim((string)$this->data[$field]) === '') {
            $this->errors[$field][] = "Le champ '{$label}' est requis.";
        }
        return $this;
    }

    /**
     * Valider qu'un champ est une chaîne
     */
    public function string(string $field, string $label = ''): self
    {
        if (isset($this->data[$field]) && !is_string($this->data[$field])) {
            $this->errors[$field][] = "Le champ '{$label}' doit être une chaîne de caractères.";
        }
        return $this;
    }

    /**
     * Valider la longueur minimale
     */
    public function minLength(string $field, int $min, string $label = ''): self
    {
        if (isset($this->data[$field]) && strlen(trim($this->data[$field])) < $min) {
            $this->errors[$field][] = "Le champ '{$label}' doit contenir au moins {$min} caractères.";
        }
        return $this;
    }

    /**
     * Valider la longueur maximale
     */
    public function maxLength(string $field, int $max, string $label = ''): self
    {
        if (isset($this->data[$field]) && strlen(trim($this->data[$field])) > $max) {
            $this->errors[$field][] = "Le champ '{$label}' ne peut pas dépasser {$max} caractères.";
        }
        return $this;
    }

    /**
     * Valider un email
     */
    public function email(string $field, string $label = ''): self
    {
        if (isset($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field][] = "Le champ '{$label}' doit être un email valide.";
        }
        return $this;
    }

    /**
     * Valider un format numérique
     */
    public function numeric(string $field, string $label = ''): self
    {
        if (isset($this->data[$field]) && !is_numeric($this->data[$field])) {
            $this->errors[$field][] = "Le champ '{$label}' doit être un nombre.";
        }
        return $this;
    }

    /**
     * Valider que la valeur est entre min et max
     */
    public function between(string $field, float $min, float $max, string $label = ''): self
    {
        if (isset($this->data[$field]) && is_numeric($this->data[$field])) {
            $val = (float)$this->data[$field];
            if ($val < $min || $val > $max) {
                $this->errors[$field][] = "Le champ '{$label}' doit être entre {$min} et {$max}.";
            }
        }
        return $this;
    }

    /**
     * Valider une date
     */
    public function date(string $field, string $format = 'Y-m-d', string $label = ''): self
    {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            $d = \DateTime::createFromFormat($format, $this->data[$field]);
            if (!$d || $d->format($format) !== $this->data[$field]) {
                $this->errors[$field][] = "Le champ '{$label}' doit être une date valide (format: {$format}).";
            }
        }
        return $this;
    }

    /**
     * Valider que la valeur est dans une liste
     */
    public function inList(string $field, array $list, string $label = ''): self
    {
        if (isset($this->data[$field]) && !in_array($this->data[$field], $list)) {
            $this->errors[$field][] = "Le champ '{$label}' doit être l'une des valeurs: " . implode(', ', $list) . ".";
        }
        return $this;
    }

    /**
     * Valider un identifiant à 10 chiffres
     */
    public function identifiant10(string $field, string $label = ''): self
    {
        if (isset($this->data[$field]) && !preg_match('/^\d{10}$/', $this->data[$field])) {
            $this->errors[$field][] = "Le champ '{$label}' doit être un identifiant à 10 chiffres.";
        }
        return $this;
    }

    /**
     * Valider un téléphone tunisien
     */
    public function telephone(string $field, string $label = ''): self
    {
        if (isset($this->data[$field]) && !preg_match('/^\+216\d{8}$/', $this->data[$field])) {
            $this->errors[$field][] = "Le champ '{$label}' doit être un numéro tunisien valide (+216XXXXXXXX).";
        }
        return $this;
    }

    /**
     * Valider un mot de passe (min 8 car, 1 maj, 1 min, 1 chiffre)
     */
    public function password(string $field, string $label = ''): self
    {
        if (isset($this->data[$field])) {
            $pwd = $this->data[$field];
            if (strlen($pwd) < 8) {
                $this->errors[$field][] = "Le mot de passe doit contenir au moins 8 caractères.";
            }
            if (!preg_match('/[A-Z]/', $pwd)) {
                $this->errors[$field][] = "Le mot de passe doit contenir au moins une majuscule.";
            }
            if (!preg_match('/[a-z]/', $pwd)) {
                $this->errors[$field][] = "Le mot de passe doit contenir au moins une minuscule.";
            }
            if (!preg_match('/[0-9]/', $pwd)) {
                $this->errors[$field][] = "Le mot de passe doit contenir au moins un chiffre.";
            }
        }
        return $this;
    }

    /**
     * Vérifier si la validation a échoué
     */
    public function fails(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Obtenir les erreurs
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Valider et retourner un message d'erreur ou null
     */
    public function validate(): ?array
    {
        return $this->fails() ? $this->errors : null;
    }
}