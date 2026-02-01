// lastEvento.tsx
import AsyncStorage from "@react-native-async-storage/async-storage";

export async function setLastEventoId(eventoId: number, userId?: string | null) {
  const key = userId ? `lastEventoId:${userId}` : "lastEventoId";
  await AsyncStorage.setItem(key, String(eventoId));
}

export async function getLastEventoId(userId?: string | null) {
  const key = userId ? `lastEventoId:${userId}` : "lastEventoId";
  const value = await AsyncStorage.getItem(key);
  return value ? Number(value) : null;
}

// ✅ NOVO: salvar nome do evento
export async function setLastEventoNome(eventoNome: string, userId?: string | null) {
  const key = userId ? `lastEventoNome:${userId}` : "lastEventoNome";
  await AsyncStorage.setItem(key, eventoNome);
}

// ✅ NOVO: obter nome do evento
export async function getLastEventoNome(userId?: string | null) {
  const key = userId ? `lastEventoNome:${userId}` : "lastEventoNome";
  const value = await AsyncStorage.getItem(key);
  return value ?? null;
}
