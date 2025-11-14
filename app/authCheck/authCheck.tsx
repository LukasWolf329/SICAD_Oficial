import AsyncStorage from "@react-native-async-storage/async-storage";
import { useRouter } from "expo-router";
import { useEffect } from "react";

export function authCheck() {
    const router = useRouter();

    useEffect(() => {
        async function verify() {
            const token = await AsyncStorage.getItem("userToken");

            if (token) {
                router.replace("/(tabs)/(painel)/home/page");
            }
        }

        verify();
    }, []);
}
