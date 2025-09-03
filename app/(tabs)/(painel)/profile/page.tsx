import "../../../../style/global.css";

import React from 'react';
import { Text, View, Image, ScrollView, Pressable } from 'react-native';
import { Feather, Ionicons } from '@expo/vector-icons';

import { Mainframe, NavBar, SideBar } from '../../../../components/NavBar';

export default function Profile() {
  return (
    <ScrollView className="flex-1 bg-white dark:bg-black">
        <NavBar></NavBar>
        
        <SideBar></SideBar>

        <Mainframe></Mainframe>
    </ScrollView>
  );
}
