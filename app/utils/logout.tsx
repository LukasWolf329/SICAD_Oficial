import AsyncStorage from "@react-native-async-storage/async-storage";
import { router } from "expo-router";

export async function logout() {
  const userId = await AsyncStorage.getItem("userId");

  const keysToRemove = [
    "userToken",
    "userName",
    "userId",
    "lastEventoId",      // caso você tenha salvo sem userId em algum momento
    "lastEventoNome",    // idem
  ];

  // remove também as chaves por usuário, se existir userId
  if (userId) {
    keysToRemove.push(`lastEventoId:${userId}`);
    keysToRemove.push(`lastEventoNome:${userId}`);
  }

  await AsyncStorage.multiRemove(keysToRemove);

  router.replace("/(tabs)/(auth)/signin/page");
}