import AsyncStorage from "@react-native-async-storage/async-storage";
import { useRouter, useSegments } from "expo-router";
import { useEffect } from "react";

export function useAuthCheck() {
  const router = useRouter();
  const segments = useSegments();

  // ✅ Normaliza o tipo (resolve o "never")
  const seg = Array.from(segments) as string[];

  useEffect(() => {
    let cancelled = false;

    (async () => {
      const token = await AsyncStorage.getItem("userToken");
      if (cancelled) return;

      const inAuth = seg.includes("(auth)");
      const inPainel = seg.includes("(painel)");

      // ✅ evita loop: só redireciona quando estiver no "grupo errado"
      if (token && inAuth) {
        router.replace("/(tabs)/(painel)/home/page");
        return;
      }

      if (!token && inPainel) {
        router.replace("/(tabs)/(auth)/signin/page");
        return;
      }
    })();

    return () => {
      cancelled = true;
    };
    // ✅ depende de uma string estável (não do array)
  }, [router, seg.join("/")]);
}