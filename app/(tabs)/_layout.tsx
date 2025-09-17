import "../../style/global.css";
import { Stack } from "expo-router";

export default function RootLayout() {
  return (
    <Stack>
      <Stack.Screen
        name="index"
        options={{
          headerShown: false,
        }}
      />
      <Stack.Screen
        name="(auth)/signup/page"
        options={{
          headerShown: false,
        }}
      />
      <Stack.Screen
        name="(auth)/signin/page"
        options={{
          headerShown: false,
        }}
      />
      <Stack.Screen
        name="(painel)/profile/page"
        options={{
          headerShown: false,
        }}
      />
      <Stack.Screen
        name="(painel)/profile/page-org"
        options={{
          headerShown: false,
        }}
      />
      <Stack.Screen
        name="(painel)/certificate/certificates"
        options={{ headerShown: false }}
      />
      <Stack.Screen
        name="(painel)/certificate/certicateScreen"
        options={{ headerShown: false }}
      />
    </Stack>
    
  );
}
