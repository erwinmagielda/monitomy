function normaliseClickAction(array $payload): string {
  $allowedActions = [
    'shop',
    'subscribe',
    'spotify'
  ];

  $action = trim((string)($payload['action'] ?? 'unknown'));
  $lowerAction = strtolower($action);

  if (in_array($lowerAction, $allowedActions, true)) {
    return $lowerAction;
  }

  return 'other';
}