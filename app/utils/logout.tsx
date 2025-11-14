import AsyncStorage from "@react-native-async-storage/async-storage";
import { router } from "expo-router";

export async function logout() {
    await AsyncStorage.removeItem("userToken");
    router.replace("/(tabs)/(auth)/signin/page");
}
