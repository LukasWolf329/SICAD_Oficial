import { ThemeProvider, DarkTheme, DefaultTheme, Link } from '@react-navigation/native';
import { useColorScheme } from '@/hooks/useColorScheme';
import { Stack } from 'expo-router';
import { NavBar, SideBar, SideBarCategory } from "@/components/NavBar";
import { View } from 'react-native';

export default function AppLayout() {
  const colorScheme = useColorScheme();

  return (
    <ThemeProvider value={colorScheme === 'dark' ? DarkTheme : DefaultTheme}>
      <Stack.Screen name="index" options={{ headerShown: false }} />
      <View className="flex-1 dark:bg-[#121212]">
        <NavBar />
        <View className="flex-1 flex-row">
          <SideBar>
            <SideBarCategory
              titulo="Gestão"
              itens={[
                { nome: "Inicio", icone: "home-outline", link: "../home/page" },
                { nome: "Pessoas", icone: "people", link: "../peoples/page" },
              ]}
              
            />
            <SideBarCategory
              titulo="Pós-Evento"
              itens={[
                { nome: "Certificados", icone: "map", link: "../certificate/page" },
              ]}
            />
            <SideBarCategory
              titulo="Geral"
              itens={[
                { nome: "Configuração", icone: "settings-outline", link: "../settings/page" },
              ]}
            />
          </SideBar>

          {/* Conteúdo principal */}
          <View className="flex-1">
            <Stack screenOptions={{ headerShown: false }} />
          </View>
        </View>
      </View>
    </ThemeProvider>
  );
}
