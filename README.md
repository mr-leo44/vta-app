# VTA API Documentation

API REST pour la gestion des vols, avions, opérateurs et justifications de vol.

## 📋 Table des matières

- [Informations générales](#informations-générales)
- [Authentication](#authentication)
- [Endpoints](#endpoints)
  - [Authentication](#authentication-1)
  - [Aircrafts](#aircrafts)
  - [Aircraft Types](#aircraft-types)
  - [Operators](#operators)
  - [Flights](#flights)
  - [Flight Justifications](#flight-justifications)
- [Modèles de données](#modèles-de-données)
- [Codes d'erreur](#codes-derreur)


## Informations générales

**Version**: 0.0.1  
**Base URL**: `http://localhost:8000/api` (En local) 
**Format**: JSON

## Authentication

L'API utilise l'authentification Bearer Token. Incluez le token dans le header de vos requêtes :

```
Authorization: Bearer {your_token}
```

### Login
```http
POST /login
Content-Type: application/json

{
  "username": "string",
  "password": "string"
}
```

**Réponse**: Retourne un token d'authentification

### Logout
```http
POST /logout
Authorization: Bearer {token}
```

## Endpoints

### Aircrafts

#### Lister tous les avions
```http
GET /aircrafts
```

#### Rechercher un avion par immatriculation
```http
GET /aircrafts/search?term={immatriculation}
```

#### Lister les avions par opérateur
```http
GET /operators/{operatorId}/aircrafts
```

#### Afficher un avion
```http
GET /aircrafts/{aircraft}
```

#### Créer un avion
```http
POST /aircrafts
Content-Type: application/json

{
  "immatriculation": "string",
  "pmad": integer|null,
  "in_activity": boolean,
  "aircraft_type_id": integer,
  "operator_id": integer
}
```

#### Mettre à jour un avion
```http
PUT /aircrafts/{aircraft}
Content-Type: application/json

{
  "immatriculation": "string",
  "pmad": integer,
  "in_activity": boolean,
  "aircraft_type_id": integer,
  "operator_id": integer
}
```

#### Supprimer un avion
```http
DELETE /aircrafts/{aircraft}
```

### Aircraft Types

#### Lister tous les types d'avions
```http
GET /aircraft-types
```

#### Rechercher un type d'avion
```http
GET /aircraft-types/find/{query}
```

#### Créer un type d'avion
```http
POST /aircraft-types
Content-Type: application/json

{
  "name": "string",
  "sigle": "string"
}
```

#### Mettre à jour un type d'avion
```http
PUT /aircraft-types/{aircraftType}
Content-Type: application/json

{
  "name": "string",
  "sigle": "string"
}
```

#### Supprimer un type d'avion
```http
DELETE /aircraft-types/{aircraftType}
```

### Operators

#### Lister tous les opérateurs
```http
GET /operators
```

#### Rechercher un opérateur
```http
GET /operators/search?term={name_or_iata}
```

#### Afficher un opérateur
```http
GET /operators/{operator}
```

#### Créer un opérateur
```http
POST /operators
Content-Type: application/json

{
  "name": "string",
  "sigle": "string",
  "iata_code": "string|null",
  "icao_code": "string|null",
  "country": "string|null",
  "flight_type": "regular|non_regular",
  "flight_nature": "commercial|non_commercial"
}
```

#### Mettre à jour un opérateur
```http
PUT /operators/{operator}
Content-Type: application/json

{
  "name": "string",
  "sigle": "string",
  "iata_code": "string|null",
  "icao_code": "string|null",
  "country": "string|null",
  "flight_type": "regular|non_regular",
  "flight_nature": "commercial|non_commercial"
}
```

#### Supprimer un opérateur
```http
DELETE /operators/{operator}
```

### Flights

#### Lister tous les vols (paginé)
```http
GET /flights
```

**Réponse**: Objet paginé avec `data`, `links` et `meta`

#### Afficher un vol
```http
GET /flights/{flight}
```

#### Créer un vol
```http
POST /flights
Content-Type: application/json

{
  "flight_number": "string",
  "operator_id": integer,
  "aircraft_id": integer,
  "departure": ["string"],
  "arrival": ["string"],
  "departure_time": "datetime",
  "arrival_time": "datetime",
  "remarks": "string|null",
  "statistics": ["string"]|null
}
```

#### Mettre à jour un vol
```http
PUT /flights/{flight}
Content-Type: application/json

{
  "flight_number": "string",
  "operator_id": integer,
  "aircraft_id": integer,
  "departure": ["string"],
  "arrival": ["string"],
  "departure_time": "datetime",
  "arrival_time": "datetime",
  "remarks": "string|null",
  "statistics": ["string"]|null
}
```

#### Supprimer un vol
```http
DELETE /flights/{flight}
```

### Flight Justifications

#### Lister toutes les justifications
```http
GET /flight-justifications
```

#### Créer une justification
```http
POST /flight-justifications
Content-Type: application/json

{
  "name": "string"
}
```

#### Mettre à jour une justification
```http
PUT /flight-justifications/{flightJustification}
Content-Type: application/json

{
  "name": "string"
}
```

#### Supprimer une justification
```http
DELETE /flight-justifications/{flightJustification}
```

## Modèles de données

### FlightResource
```json
{
  "id": integer,
  "flight_number": "string",
  "operator": {
    "name": "string",
    "sigle": "string"
  },
  "aircraft": "string",
  "flight_regime": "domestic|international",
  "flight_type": "regular|non_regular",
  "flight_nature": "commercial|non_commercial",
  "status": "qrf|prevu|atteri|annule|detourne",
  "departure": [],
  "arrival": [],
  "departure_time": "datetime",
  "arrival_time": "datetime",
  "remarks": "string|null",
  "statistics": {
    "passengers_count": integer,
    "pax_bus": integer,
    "go_pass_count": integer,
    "fret_count": [],
    "excedents": [],
    "passengers_ecart": integer,
    "has_justification": boolean,
    "justification": []
  }
}
```

### OperatorResource
```json
{
  "id": integer,
  "name": "string",
  "sigle": "string",
  "iata_code": "string|null",
  "icao_code": "string|null",
  "country": "string|null",
  "flight_type": {
    "value": "string",
    "label": "string"
  },
  "flight_nature": {
    "value": "string",
    "label": "string"
  },
  "created_at": "datetime",
  "updated_at": "datetime"
}
```

## Codes d'erreur

- **200**: OK
- **201**: Créé avec succès
- **204**: Pas de contenu (succès de suppression)
- **401**: Non authentifié
- **404**: Ressource non trouvée
- **422**: Erreur de validation

### Format d'erreur de validation (422)
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "field_name": [
      "Error message"
    ]
  }
}
```

### Format d'erreur 404
```json
{
  "message": "Resource not found"
}
```

## Exemples d'utilisation

### Créer un vol avec cURL
```bash
curl -X POST http://localhost:8000/api/flights \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "flight_number": "AF001",
    "operator_id": 1,
    "aircraft_id": 1,
    "departure": ["CDG"],
    "arrival": ["JFK"],
    "departure_time": "2024-01-15T10:00:00Z",
    "arrival_time": "2024-01-15T14:00:00Z"
  }'
```

## Support

Pour toute question ou problème, veuillez contacter l'équipe de développement.
